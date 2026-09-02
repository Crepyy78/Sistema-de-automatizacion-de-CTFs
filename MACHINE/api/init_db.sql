CREATE DATABASE IF NOT EXISTS apiDB;
USE apiDB

CREATE OR REPLACE USER 'basicUser'@'localhost' IDENTIFIED BY 'LOL';
GRANT ALL PRIVILEGES ON apiDB.* TO 'basicUser'@'localhost';
FLUSH PRIVILEGES;

CREATE TABLE IF NOT EXISTS USERS (
    id_user INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    salt TINYBLOB NOT NULL,
    totpSecret VARCHAR(255) DEFAULT NULL,
    isAdmin BOOLEAN DEFAULT FALSE,
    isCreator BOOLEAN DEFAULT FALSE,
    points INT DEFAULT 0
);

CREATE TABLE IF NOT EXISTS TEAMS(
    id_teams INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL UNIQUE,
    description VARCHAR(1000),
    leader INT NOT NULL,
    points INT DEFAULT 0,
    invitanionalCode VARCHAR(255) UNIQUE,
    codeExpireDate TIMESTAMP,
    isPersonal BOOLEAN NOT NULL DEFAULT FALSE,
    FOREIGN KEY (leader) REFERENCES USERS(id_user)
);


CREATE TABLE IF NOT EXISTS EVENTS(
    id_event INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL UNIQUE,
    startDate DATETIME NOT NULL,
    endDate DATETIME NOT NULL,
    description VARCHAR(1000),
    maxNumbersPerTeam  INT NOT NULL,
    isPublic  BOOLEAN NOT NULL DEFAULT FALSE,
    invitanionalCode VARCHAR(255) UNIQUE,
    codeExpireDate TIMESTAMP
);

CREATE TABLE IF NOT EXISTS USERS_EVENTS(
    id_user INT NOT NULL,
    id_event INT NOT NULL,
    points INT DEFAULT 0,
    isMantainer BOOLEAN NOT NULL,
    PRIMARY KEY (id_user, id_event),
    FOREIGN KEY (id_user) REFERENCES USERS(id_user) ON DELETE CASCADE,
    FOREIGN KEY (id_event) REFERENCES EVENTS(id_event) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS CHALLENGES(
    id_challenge INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL UNIQUE,
    description VARCHAR(1000),
    category VARCHAR(255), 
    points INT DEFAULT 0,
    difficulty VARCHAR(50),
    author VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS CHALLENGES_USING(
    id_challengeU INT NOT NULL,
    id_challenge INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description VARCHAR(1000),
    category VARCHAR(255), 
    points INT DEFAULT 0,
    difficulty VARCHAR(50),
    author VARCHAR(255),
    PRIMARY KEY (id_challengeU, id_challenge),
    FOREIGN KEY (id_challenge) REFERENCES CHALLENGES(id_challenge) ON DELETE CASCADE
);


CREATE TABLE IF NOT EXISTS CHALLENGES_EVENT(
    id_challengeU INT NOT NULL,
    id_event INT NOT NULL,
    PRIMARY KEY (id_challengeU, id_event),
    FOREIGN KEY (id_challengeU) REFERENCES CHALLENGES_USING(id_challengeU) ON DELETE CASCADE,
    FOREIGN KEY (id_event) REFERENCES EVENTS(id_event) ON DELETE CASCADE  
);

CREATE TABLE IF NOT EXISTS TEAMS_CHALLENGES_EVENT(
    id_teams INT NOT NULL,
    id_challenge INT NOT NULL,
    id_event INT NOT NULL,
    status VARCHAR(20) DEFAULT 'not done',
    PRIMARY KEY (id_teams, id_challenge, id_event),
    FOREIGN KEY (id_teams) REFERENCES TEAMS(id_teams) ON DELETE CASCADE,
    FOREIGN KEY (id_challenge) REFERENCES CHALLENGES(id_challenge) ON DELETE CASCADE,
    FOREIGN KEY (id_event) REFERENCES EVENTS(id_event) ON DELETE CASCADE,
    CHECK (status IN ('done', 'not done'))  
);

CREATE TABLE IF NOT EXISTS USER_CONTAINERES(
    id_container INT NOT NULL AUTO_INCREMENT,
    id_user INT NOT NULL,
    id_event INT NOT NULL,
    challengeName VARCHAR(255) NOT NULL,
    port INT DEFAULT 80,
    flag VARCHAR(255) NOT NULL,
    instanceId VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL,
    PRIMARY KEY (id_container,id_user),
    FOREIGN KEY (id_user) REFERENCES USERS(id_user) ON DELETE CASCADE,
    FOREIGN KEY (id_event) REFERENCES EVENTS(id_event) ON DELETE CASCADE,
    CHECK (status IN ('running', 'pause'))
);

CREATE TABLE IF NOT EXISTS EVENTS_TEAMS(
    id_teams INT NOT NULL,
    id_event INT NOT NULL,
    points INT DEFAULT 0,
    lastChallengeDate DATETIME DEFAULT NULL,
    PRIMARY KEY (id_teams, id_event),
    FOREIGN KEY (id_teams) REFERENCES TEAMS(id_teams) ON DELETE CASCADE,
    FOREIGN KEY (id_event) REFERENCES EVENTS(id_event) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS USERS_TEAMS(
    id_teams INT NOT NULL,
    id_user INT NOT NULL,
    PRIMARY KEY (id_teams, id_user),
    FOREIGN KEY (id_teams) REFERENCES TEAMS(id_teams) ON DELETE CASCADE,
    FOREIGN KEY (id_user) REFERENCES USERS(id_user) ON DELETE CASCADE
);