CREATE TABLE notifications
(
  id serial,
	"type" varchar(20) NOT NULL,
	description varchar(200) NOT NULL,
  created integer NOT NULL,
  processed integer NULL,
	CONSTRAINT notifications_id_pkey PRIMARY KEY (id)
);

CREATE INDEX type_idx ON notifications (type);

