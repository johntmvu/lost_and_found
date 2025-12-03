USE campus_lost_found;

INSERT INTO `User` (name, email) VALUES
('Alice Student','alice@example.edu'),
('Bob Faculty','bob@example.edu'),
('Carla Admin','carla.admin@example.edu'),
('David Grad','david.grad@example.edu'),
('Eve Staff','eve.staff@example.edu');

INSERT INTO Location (building, room) VALUES
('Science Hall','Room 101'),
('Library','Main Hall');

-- additional locations
INSERT INTO Location (building, room) VALUES
('Student Center','Lost & Found Desk'),
('Gymnasium','Front Desk'),
('Cafeteria','Main Hall');

INSERT INTO Item (title, description) VALUES
('Black Backpack','Black backpack with a laptop and water bottle'),
('Set of Keys','A bunch of keys with a red keychain'),
('Blue Umbrella','Compact blue umbrella, wooden handle'),
('Wireless Earbuds','Left earbud missing rubber tip'),
('Water Bottle','Insulated stainless steel bottle with sticker');

-- Link posts: user 1 posted item 1, user 2 posted item 2
INSERT INTO Posts (user_id, item_id) VALUES
(1,1),(2,2),(3,3),(4,4),(5,5);

-- Link items to locations
INSERT INTO At (location_id, item_id) VALUES
(1,1),(2,2),(3,3),(4,4),(5,5);

-- Add a claim for item 1
INSERT INTO Claim (description, photo) VALUES
('I lost my black backpack yesterday',''),
('Someone found earbuds in the library',''),
('Left umbrella near gym entrance','');

-- Link claim submitter
INSERT INTO Submits (user_id, claim_id) VALUES
(1,1),(5,2),(4,3);

-- Target mapping claim->item
INSERT INTO Targets (item_id, claim_id) VALUES
(1,1),(4,2),(3,3);

-- A few more arbitrary sample inserts for variety
INSERT INTO `User` (name, email) VALUES ('Frank Student','frank@student.example.edu');
INSERT INTO Item (title, description) VALUES ('Red Scarf','Wool scarf, red with white stripes');
INSERT INTO Posts (user_id, item_id) VALUES (6,6);
INSERT INTO At (location_id, item_id) VALUES (2,6);
