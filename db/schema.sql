
CREATE TABLE country (
	code CHAR(2) PRIMARY KEY,
	name VARCHAR(150) NOT NULL
);

CREATE TABLE ip2country (
	begin  UNSIGNED INTEGER PRIMARY KEY,
	end    UNSIGNED INTEGER NOT NULL,
	country_code CHAR(2) NOT NULL REFERENCES country(code)
);

CREATE INDEX end_ix ON ip2country (end);

