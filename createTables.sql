CREATE TABLE tenders (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tender_number VARCHAR(255) NOT NULL,
    organizer_name VARCHAR(255) NOT NULL,
    tender_view_url VARCHAR(255) NOT NULL,
    date DATETIME NOT NULL
);

CREATE TABLE tender_files (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tender_id INT(11) UNSIGNED NOT NULL,
    href VARCHAR(255) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    FOREIGN KEY (tender_id) REFERENCES tenders(id)
);