DROP TABLE IF EXISTS devices_hotel;

CREATE TABLE devices_hotel(
    device_id INT AUTO_INCREMENT,
    device_ip CHAR(15) NOT NULL,
    photo_path VARCHAR(70),
    number_of_photos INT NOT NULL,
    embeddings_path VARCHAR(70),
    recognition_status VARCHAR(18) NOT NULL,
    guest_name VARCHAR(20) NOT NULL,
    PRIMARY KEY (device_id),
    UNIQUE (device_ip),
    UNIQUE (guest_name)
);

-- Those are all the recognition_status states:
-- NO-PHOTO
-- PHOTO-UPLOADED
-- EMBEDDINGS-READY
-- WAITING-ACTIVATION
-- DEVICE-ACTIVE