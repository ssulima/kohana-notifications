CREATE TABLE notifications
(
    id serial,
    "type" varchar(20) NOT NULL,
    description varchar(200) NOT NULL,
    created integer NOT NULL,
    processed integer NULL,
    CONSTRAINT notifications_id_pkey PRIMARY KEY (id)
);

CREATE TABLE notifications_mail_queue
(
    id serial,
    notification_id int(11) NOT NULL,
    recipient varchar(200) NOT NULL,
    processed integer NULL,
    CONSTRAINT notifications_mail_queue_id_pkey PRIMARY KEY (id)
);

CREATE INDEX type_idx ON notifications (type);

