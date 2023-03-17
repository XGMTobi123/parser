<?php
require_once 'vendor/autoload.php';

use Nesk\Puphpeteer\Puppeteer;
use Nesk\Rialto\Data\JsFunction;
//Данные БД
$host = 'localhost';
$username = 'root';
$password = '';
$dbname = 'parser';
//
$puppeteer = new Puppeteer;
$browser = $puppeteer->launch();
$page = $browser->newPage();
$page->setRequestInterception(true);
$url = 'https://tender.rusal.ru/Tenders/Load';
$elementsLimit = 3;
$postData = "offset=0&limit=$elementsLimit&sortColumn=&sortAsc=&MultiString=&__AllowedTenderConfigCodes=&IntervalRequestReceivingBeginDate.BeginDate=&IntervalRequestReceivingBeginDate.EndDate=&IntervalRequestReceivingEndDate.BeginDate=&IntervalRequestReceivingEndDate.EndDate=&IntervalBidReceivingBeginDate.BeginDate=&IntervalBidReceivingBeginDate.EndDate=&ClassifiersFieldData.SiteSectionType=bef4c544-ba45-49b9-8e91-85d9483ff2f6&ClassifiersFieldData.ClassifiersFieldData.__SECRET_DO_NOT_USE_OR_YOU_WILL_BE_FIRED=&OrganizerData=";
$page->on('request', new JsFunction(
    ['interceptedRequest'],
    "
var data = {
'method': 'POST',
'postData': '$postData',
'headers': {
'Content-Type': 'application/x-www-form-urlencoded'
},
};
interceptedRequest.continue(data);
"
));
$page->goto($url);
$content = $page->content();
$content = strip_tags($content);
$data = json_decode($content, true);
$data = $data['Rows'];

$cleanData = [];
foreach ($data as $key => $value) {
    if ($value['TenderNumber']) {
        $cleanData[$key]['tenderNumber'] = $value['TenderNumber'];
    }
    if ($value['OrganizerName']) {
        $cleanData[$key]['organizerName'] = $value['OrganizerName'];
    }
    if ($value['TenderViewUrl']) {
        $cleanData[$key]['tenderViewUrl'] = "https://tender.rusal.ru" . $value['TenderViewUrl'];
    }
    $page = $browser->newPage();
    $page->goto($cleanData[$key]['tenderViewUrl']);
    $page->waitForSelector('.control-readonly');
    $content = trim($page->evaluate('document.querySelector("div.control-readonly[data-field-name=\'Fields.RequestReceivingBeginDate\']").innerHTML'));
    $cleanData[$key]['date'] = $content;
    $links = $page->querySelectorAll('.file-download-link');
    if ($links) {
        foreach ($links as $linkkey => $link) {
            $cleanData[$key]['files'][$linkkey]['href'] = $link->getProperty('href')->jsonValue();
            $cleanData[$key]['files'][$linkkey]['filename'] = trim($link->getProperty('textContent')->jsonValue());
        }
    }
}
?>
    <table>
        <thead>
        <tr>
            <th>Tender Number</th>
            <th>Organizer Name</th>
            <th>Tender View Url</th>
            <th>Date</th>
            <th>Files</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($cleanData as $row): ?>
            <tr>
                <td><?php echo $row['tenderNumber']; ?></td>
                <td><?php echo $row['organizerName']; ?></td>
                <td><a href="<?php echo $row['tenderViewUrl']; ?>"><?php echo $row['tenderNumber']; ?></a></td>
                <td><?php echo $row['date']; ?></td>
                <td>
                    <?php if (isset($row['files'])): ?>
                        <ul>
                            <?php foreach ($row['files'] as $file): ?>
                                <li><a href="<?php echo $file['href']; ?>"><?php echo $file['filename']; ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php

$conn = new mysqli($host, $username, $password, $dbname);
$conn->set_charset('utf8mb4');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

//очистка БД для теста
$deletequery = "DELETE from `tenders` WHERE id>0";
$conn->query($deletequery);
$deletequery = "ALTER TABLE tenders AUTO_INCREMENT=1";
$conn->query($deletequery);
$deletequery = "DELETE from `tender_files` WHERE id>0";
$conn->query($deletequery);
$deletequery = "ALTER TABLE `tender_files` AUTO_INCREMENT = 1;";
$conn->query($deletequery);
//
foreach ($cleanData as $key => $tender) {
    $tenderNumber = $tender['tenderNumber'];
    $organizerName = $tender['organizerName'];
    $tenderViewUrl = $tender['tenderViewUrl'];
    $date = convertDateToMySQLFormat($tender['date']);
    $sql = "INSERT INTO tenders (tender_number, organizer_name, tender_view_Url, date) 
            VALUES ('$tenderNumber', '$organizerName', '$tenderViewUrl', '$date')";
    if ($conn->query($sql) === FALSE) {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
    if (isset($tender['files'])) {
        foreach ($tender['files'] as $file) {
            $sql = "SELECT id FROM tenders WHERE tender_number = '{$tender['tenderNumber']}'";
            $result = $conn->query($sql);
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $tenderid = $row["id"];
            } else {
                echo "0 results";
            }
            $href = $file['href'];
            $filename = $file['filename'];
            $filesql = "INSERT INTO tender_files (tender_id, href, filename)
                    VALUES ('$tenderid','$href','$filename')";
            if ($conn->query($filesql) === FALSE) {
                echo "Error: " . $filesql . "<br>" . $conn->error;
            }
        }

    }

}

$conn->close();
$browser->close();
function convertDateToMySQLFormat($dateString)
{
    $dateTime = DateTime::createFromFormat('d.m.Y H:i', $dateString);
    return $dateTime->format('Y-m-d H:i:s');
}