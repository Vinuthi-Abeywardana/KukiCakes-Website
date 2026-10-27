-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: kukicakes_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `kukicakes_db`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `kukicakes_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `kukicakes_db`;

--
-- Table structure for table `administrator`
--

DROP TABLE IF EXISTS `administrator`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `administrator` (
  `admin_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL COMMENT 'PHP bcrypt hash — use password_hash() / password_verify()',
  `email` varchar(150) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `uq_administrator_user` (`username`),
  UNIQUE KEY `uq_administrator_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Admin user accounts for the KukiCakes admin panel';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `administrator`
--

LOCK TABLES `administrator` WRITE;
/*!40000 ALTER TABLE `administrator` DISABLE KEYS */;
INSERT INTO `administrator` VALUES (1,'kuki_admin','$2y$10$cAcSecTUzlmRlVsF4pln8eEGePa2TmDmVpVKe2ejw1TBENGlhqUVu','admin@kukicakes.lk','2026-09-14 12:34:23');
/*!40000 ALTER TABLE `administrator` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cake`
--

DROP TABLE IF EXISTS `cake`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cake` (
  `cake_id` int(11) NOT NULL AUTO_INCREMENT,
  `cake_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `sale_unit` varchar(50) DEFAULT NULL,
  `confirmed_size` varchar(100) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL COMMENT 'File path or external URL',
  `availability` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = available, 0 = unavailable',
  `stock_quantity` int(11) DEFAULT NULL,
  `badge` varchar(50) DEFAULT NULL COMMENT 'e.g. Bestseller, New, Seasonal, Kids, 21st',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cake_id`),
  CONSTRAINT `chk_cake_price` CHECK (`price` >= 0),
  CONSTRAINT `chk_cake_avail` CHECK (`availability` in (0,1))
) ENGINE=InnoDB AUTO_INCREMENT=134 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cake products available in the KukiCakes catalog';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cake`
--

LOCK TABLES `cake` WRITE;
/*!40000 ALTER TABLE `cake` DISABLE KEYS */;
INSERT INTO `cake` VALUES (1,'Golden Pearl Anniversary Cake','A tall ivory buttercream cake scattered with cascading gold sugar pearls and flanked by deep red rose clusters, topped with a gold script \"Happy Anniversary\" topper.',10300.00,'Whole cake','1.5 kg, round, tall buttercream','docs/cakes/Anniversary/Anniversary cake1.jpeg',1,NULL,'Bestseller','2026-10-04 02:07:19'),(2,'Burgundy Ombre Heart Anniversary Cake','A two-tone burgundy-to-ivory ombré buttercream cake finished with a jagged gold-leaf border and gold sugar pearls, accented with double gold wire hearts and a black \"Happy Anniversary\" topper.',10100.00,'Whole cake','1.5 kg, round, ombre buttercream','docs/cakes/Anniversary/Anniversary cake2.jpeg',1,NULL,'anniversary','2026-10-04 02:07:19'),(3,'Magenta Hydrangea Anniversary Cake','An ivory buttercream cake with fine wave piping and scattered pearls, decorated with magenta hydrangea blooms and gold spheres, finished with a gold script \"Wedding Anniversary\" topper.',11000.00,'Whole cake','1.5 kg, round, piped buttercream','docs/cakes/Anniversary/Anniversary cake3.jpeg',1,NULL,'anniversary','2026-10-04 02:07:19'),(4,'Together Forever Gold Cascade Cake','An ivory buttercream cake finished with a dramatic gold-leaf cascade and fresh red and white roses with eucalyptus. Topped with a gold double-heart topper and \"Happy Anniversary\" script, with \"Together Forever\" written on the base board.',11400.00,'Whole cake','1.5 kg, round, gold-leaf cascade','docs/cakes/Anniversary/Anniversary cake4.jpeg',1,NULL,'anniversary','2026-10-04 02:07:19'),(5,'Golden Drip Anniversary Cake','A white buttercream cake with a dripped, melting-style texture, scattered gold leaf and sugar pearls, and red fondant hearts around the sides. Finished with a mirrored \"Happy Anniversary\" topper and \"MOM DAD\" lettering on the base',7300.00,'Whole cake','1.0 kg, round, drip','docs/cakes/Anniversary/Anniversary cake5.jpeg',1,NULL,'Seasonal','2026-10-04 02:07:19'),(6,'Modern MuseRed Anniversary Cake','A white cake with a hand-painted red ombré and scattered sugar pearls, decorated with red heart outlines, a fresh red rose and \"Happy Anniversary\" piped in red script',7100.00,'Whole cake','1.0 kg, round, hand-painted','docs/cakes/Anniversary/Anniversary cake6.jpeg',1,NULL,'New','2026-10-04 02:07:19'),(7,'SpongeBob Kids Birthday Cake','A yellow fondant cake styled as SpongeBob\'s face with a cut-out \"cheese hole\" texture, topped with SpongeBob and friends cutout toppers and a birthday age plaque.',9000.00,'Whole cake','1.0 kg, fondant character cake','docs/cakes/Birthday/Kids Birthday Cake1.jpeg',1,NULL,'Kids','2026-10-04 02:07:19'),(8,'Hello Kitty Kids Birthday Cake','A white and pink Hello Kitty themed cake with piped bows and polka dots, a Hello Kitty figure, a pink \"5\" numeral, and a \"Happy Birthday\" heart plaque.',8100.00,'Whole cake','1.0 kg, buttercream + fondant figure','docs/cakes/Birthday/Kids Birthday Cake2.jpeg',1,NULL,'Kids','2026-10-04 02:07:19'),(9,'Jungle Safari Adventure KidsBirthday Cake','A green jungle-themed cake with a painted leaf design, topped with fondant lion, monkey, giraffe and elephant figures, green balloons, and a \"2\" numeral.',10400.00,'Whole cake','1.2 kg, fondant animal figures','docs/cakes/Birthday/Kids Birthday Cake3.jpeg',1,NULL,'Kids','2026-10-04 02:07:19'),(10,'Lucas\'s Dino Volcano Kids Adventure Birthday Cake','A light-blue dinosaur-themed cake with a fondant volcano, palm leaves and colorful dinosaur figures, personalized with a \"LUCAS 3\" wood-effect plaque.',12900.00,'Whole cake','1.5 kg, fondant volcano + dinosaurs','docs/cakes/Birthday/Kids Birthday Cake4.jpeg',1,NULL,'Kids','2026-10-04 02:07:19'),(11,'Pastel Smiley Rainbow Kids Birthday Cake','A pink buttercream cake with a kawaii smiley face and blushed cheeks, finished with a shell border and fondant flower, rainbow, cloud and star toppers.',6700.00,'Whole cake','1.0 kg, buttercream kawaii face','docs/cakes/Birthday/Kids Birthday Cake5.jpg',1,NULL,'Kids','2026-10-04 02:07:19'),(12,'Spider-Man Kids Birthday Cake','A bold red Spider-Man themed cake with a hand-painted web design, a 3D Spider-Man figure, a blue \"3\" numeral, and the name \"TARIK\" in blue fondant letters.',8800.00,'Whole cake','1.0 kg, fondant, 3D figure','docs/cakes/Birthday/Kids Birthday Cake6.jpeg',1,NULL,'Kids','2026-10-04 02:07:19'),(13,'Safari Freinds Kids Birthday Cake','A buttercream cake styled with a grass texture and fondant zebra, elephant and giraffe cutouts, a smiling sun topper, pastel balloons, and a blue \"1\" numeral.',9300.00,'Whole cake','1.2 kg, buttercream + fondant animals','docs/cakes/Birthday/Kids Birthday Cake7.jpeg',1,NULL,'Kids','2026-10-04 02:07:19'),(14,'Golden 21st Birthday Cake','An ivory buttercream cake with a cascade of blush, white and gold sugar spheres, finished with a gold script \"Happy 21st Jess\" topper.',7800.00,'Whole cake','1.0 kg, round, sugar-sphere cascade','docs/cakes/Birthday/21st birthday cake1.jpeg',1,NULL,'21st','2026-10-04 02:07:19'),(15,'Black and White 21st Birthday Cake','A white buttercream cake with a piped lace border, small black fondant hearts, black ribbon bows, and \"twenty one\" written in black script.',6600.00,'Whole cake','1.0 kg, round, piped lace','docs/cakes/Birthday/21st birthday cake2.jpeg',1,NULL,'21st','2026-10-04 02:07:19'),(16,'Gold Drip 21st Birthday Cake','A matte black fondant cake with a gold-leaf paint-brush accent and a mirrored gold \"21\" topper with a drip effect, styled with lit candles.',8700.00,'Whole cake','1.0 kg, matte fondant','docs/cakes/Birthday/21st birthday cake3.jpeg',1,NULL,'21st','2026-10-04 02:07:19'),(17,'Blush Rosette Mirror 21st Cake','A cake fully covered in blush-pink and ivory buttercream rosettes, finished with a small bow and a gold mirrored \"21\" topper.',10800.00,'Whole cake','1.5 kg, full buttercream rosettes','docs/cakes/Birthday/21st birthday cake4.jpeg',1,NULL,'21st','2026-10-04 02:07:19'),(18,'Rustic Baby\'s Breath 21st Cake','An ivory textured buttercream cake decorated with dried baby\'s breath sprigs and a written \"21\" numeral, presented with a \"Happy 21st Birthday\" gift tag.',5800.00,'Whole cake','1.0 kg, textured buttercream','docs/cakes/Birthday/21st birthday cake5.jpeg',1,NULL,'21st','2026-10-04 02:07:19'),(19,'Disco Nights 21st Birthday Cake','A buttercream cake decorated with gold confetti sprinkles, gold spheres, a disco ball, and mini liquor-bottle cake toppers alongside double gold \"21\" numerals.',11200.00,'Whole cake','1.5 kg, buttercream with props','docs/cakes/Birthday/21st birthday cake6.jpeg',1,NULL,'21st','2026-10-04 02:07:19'),(20,'Golden Truffle 21st Coming-Up Cake','An ivory cake finished with a chocolate ganache drip, decorated generously with gold-wrapped chocolate truffles and wafer rolls, topped with a gold \"21st Coming Up\" script.',12300.00,'Whole cake','1.5 kg, ganache drip','docs/cakes/Birthday/21st birthday cake7.jpeg',1,NULL,'21st','2026-10-04 02:07:19'),(59,'Classic Black Forest Cake','A classic Black Forest cake with dark chocolate sponge layers, whipped cream filling, and fresh cherries on top and around the base.',6500.00,'Whole cake','1.0 kg, round','docs/cakes/Birthday/Black Forest Cake1.jpeg',1,NULL,'Classic','2026-10-17 05:21:55'),(60,'Cherry Chocolate Shavings Forest Cake','A chocolate sponge cake coated in whipped cream with dark chocolate shavings around the sides and a generous crown of fresh cherries on top.',6900.00,'Whole cake','1.0 kg, round','docs/cakes/Birthday/Black Forest Cake2.jpeg',1,NULL,'Classic','2026-10-17 05:21:55'),(61,'Triple-Layer Chocolate Rosette Cake','A multi-layer chocolate cake filled and coated in chocolate buttercream, finished with piped rosette borders top and bottom.',9500.00,'Whole cake','1.5 kg, 3-layer','docs/cakes/Birthday/Chocolate Cake1.jpeg',1,NULL,'birthday','2026-10-17 05:21:55'),(62,'Glossy Ganache Chocolate Cake','A chocolate cake with a glossy ganache drip, topped with piped chocolate buttercream swirls and chunks of dark chocolate.',7500.00,'Whole cake','1.0 kg, round','docs/cakes/Birthday/Chocolate Cake2.jpeg',1,NULL,'birthday','2026-10-17 05:21:55'),(63,'Chocolate Truffle Decadence Cake','A chocolate cake with a thick ganache drip, topped with chocolate truffle balls and chocolate curls.',8000.00,'Whole cake','1.0 kg, round','docs/cakes/Birthday/Chocolate Cake3.jpeg',1,NULL,'birthday','2026-10-17 05:21:55'),(64,'Rustic Naked Chocolate Cake','A rustic \"naked style\" chocolate layer cake with a thin chocolate frosting between layers and a light dusting of chocolate shavings on top.',6300.00,'Whole cake','1.0 kg, naked style','docs/cakes/Birthday/Chocolate Cake4.jpeg',1,NULL,'birthday','2026-10-17 05:21:55'),(65,'Golden Peach Rose 90th Birthday Cake','An ivory textured buttercream cake with a gold-leaf drizzle accent, fresh peach roses and baby\'s breath, topped with a gold \"90\" numeral.',8200.00,'Whole cake','1.0 kg, round','docs/cakes/Birthday/Old Birthday Cake1.jpeg',1,NULL,'Old','2026-10-17 05:21:55'),(66,'Golden Balloon 50th Birthday Cake','An ivory cake topped with a dramatic cluster of gold balloon-style spheres and gold bead drips, finished with a gold \"50\" numeral and paired with a champagne bottle.',8900.00,'Whole cake','1.0 kg, round','docs/cakes/Birthday/Old Birthday Cake2.jpeg',1,NULL,'Old','2026-10-17 05:21:55'),(67,'Starlight Gold 80th Birthday Cake','A white cake with a chocolate ganache drip and swirled buttercream dollops, topped with a gold \"80\" numeral and black-and-gold star picks.',7400.00,'Whole cake','1.0 kg, round','docs/cakes/Birthday/Old Birthday Cake3.jpeg',1,NULL,'Old','2026-10-17 05:21:55'),(68,'Fresh Strawberry Rosette Cake','A pale pink cake finished with piped rosette swirls and a ring of fresh whole and halved strawberries on top.',8000.00,'Whole cake','1.0 kg, round','docs/cakes/Birthday/Strawberry Cake1.jpeg',1,NULL,'strawberry','2026-10-17 05:21:55'),(69,'Strawberry Sprinkle Delight Cake','A pink buttercream cake with swirled rosette piping and red sprinkle-dot accents, topped generously with fresh strawberries.',11600.00,'Whole cake','1.5 kg, round','docs/cakes/Birthday/Strawberry Cake2.jpeg',1,NULL,'strawberry','2026-10-17 05:21:55'),(70,'Traditional Glazed Christmas Fruit Cake','A traditional dark fruit cake topped with a glossy glazed arrangement of dried and glacé fruit and nuts.',9300.00,'Whole cake','1.0 kg, dark fruit cake','docs/cakes/Christmas/Christmas Cake1.jpeg',1,NULL,'christmas','2026-10-17 05:21:55'),(71,'Festive Wreath Christmas Cake','An ivory buttercream cake finished with a chocolate ganache drip, crowned with a festive green buttercream wreath studded with red, gold and silver sugar baubles.',7300.00,'Whole cake','1.0 kg, round','docs/cakes/Christmas/Christmas Cake2.jpeg',1,NULL,'christmas','2026-10-17 05:21:55'),(72,'Buche de Noel Yule Log','A chocolate Yule log (Bûche de Noël) — a rolled sponge finished with a rich chocolate ganache bark texture, garnished with fresh cranberries and rosemary sprigs and dusted with powdered \"snow\" sugar.',9000.00,'Whole log','1 log, about 1.0 kg','docs/cakes/Christmas/Christmas Cake3.jpeg',1,NULL,'christmas','2026-10-17 05:21:55'),(73,'Snowflake & Holly Christmas Cake','An ivory buttercream cake with delicate snowflake piping on the sides, topped with a hand-piped holly wreath and a red bow.',7000.00,'Whole cake','1.0 kg, round','docs/cakes/Christmas/Christmas Cake4.jpeg',1,NULL,'christmas','2026-10-17 05:21:55'),(74,'Golden Crust Traditional Fruit Cake','A classic baked fruit cake with a deep golden-brown crust and mixed dried fruit studded throughout.',6600.00,'Whole cake','1.0 kg, baked fruit cake','docs/cakes/Christmas/Fruit Cake1.jpeg',1,NULL,'christmas','2026-10-17 05:21:55'),(75,'Blush Rose Swirl Cupcakes','Vanilla celebration cake with a custom graduation message.',3300.00,'Box of 6','Box of 6 cupcakes','docs/cakes/Cupcake/CupCakes1.jpeg',1,NULL,'New','2026-10-17 05:21:55'),(76,'Navy & Gold Wave Cupcakes','Red velvet cake with cream cheese frosting and gold accents.',4100.00,'Box of 6','Box of 6 cupcakes','docs/cakes/Cupcake/CupCakes2.jpeg',1,NULL,'New','2026-10-17 05:21:55'),(77,'Dusty Blue Rosette Cupcakes','Vanilla number cake with fresh cream, berries and macarons.',3600.00,'Box of 6','Box of 6 cupcakes','docs/cakes/Cupcake/CupCakes3.jpeg',1,NULL,'New','2026-10-17 05:21:55'),(78,'Pink Mauve Red Velvet Cupcakes','Chocolate number cake with ganache and chocolate shards.',3800.00,'Box of 6','Box of 6 cupcakes','docs/cakes/Cupcake/CupCakes4.jpeg',1,NULL,'New','2026-10-17 05:21:55'),(79,'Sparkling Pink Rose Cupcakes','Strawberry number cake with cream rosettes and fresh fruit.',3700.00,'Box of 6','Box of 6 cupcakes','docs/cakes/Cupcake/CupCakes5.jpeg',1,NULL,'New','2026-10-17 05:21:55'),(80,'Mocha & Ivory Rose Cupcakes','Red velvet number cake with cream cheese frosting and pearls.',3900.00,'Box of 6','Box of 6 cupcakes','docs/cakes/Cupcake/CupCakes6.jpeg',1,NULL,'New','2026-10-17 05:21:55'),(81,'Ivory Pearl Wedding Ring Cupcakes','Vanilla berry number cake with a bright celebration finish.',3400.00,'Box of 6','Box of 6 cupcakes','docs/cakes/Cupcake/CupCakes7.jpeg',1,NULL,'New','2026-10-17 05:21:55'),(82,'Burgundy Rose Half-Dozen Cupcakes','Funfetti sponge with vanilla buttercream and colourful confetti.',4700.00,'Box of 6','Box of 6 cupcakes','docs/cakes/Cupcake/CupCakes8.jpeg',1,NULL,'New','2026-10-17 05:21:55'),(83,'Ivory Rose Wedding Cupcake Tower','Coconut and pineapple cake with bright tropical decorations.',11200.00,'Box of 24','Tower of 24 cupcakes','docs/cakes/Cupcake/WeddingCupCakes1.jpeg',1,NULL,'New','2026-10-17 05:21:55'),(84,'Gilded Ivory Rose Wedding Cupcakes','Chocolate cake with playful decorations for a party table.',6500.00,'Box of 12','Box of 12 cupcakes','docs/cakes/Cupcake/WeddingCupCakes2.jpeg',1,NULL,'New','2026-10-17 05:21:55'),(85,'Golden Rings Engagement Cake','A two-tier ivory buttercream cake with a soft wave-textured finish and scattered pearls, topped with a gold wire \"engagement rings\" topper and gold script signage.',22500.00,'Whole cake','2-tier, about 2.5 kg','docs/cakes/Engagement/EngagementCake1.jpeg',1,NULL,'engagement','2026-10-17 05:21:55'),(86,'Draped Fringe Engagement Cake','A two-tier ivory cake with a dramatic draped fondant fringe swirling diagonally across both tiers, softened with sprays of baby\'s breath.',22500.00,'Whole cake','2-tier, about 2.5 kg','docs/cakes/Engagement/EngagementCake2.jpeg',1,NULL,'engagement','2026-10-17 05:21:55'),(87,'S&H Champagne Bubble Engagement Cake','A two-tier ivory cake with a pearl \"champagne bubble\" texture, a gold \"S&H\" monogram, and a gold interlocking rings-and-diamond topper.',23500.00,'Whole cake','2-tier, about 2.5 kg','docs/cakes/Engagement/EngagementCake3.jpeg',1,NULL,'engagement','2026-10-17 05:21:55'),(88,'Engaged in Ivory Rose Cake','A single-tier ivory cake with a soft pearl cluster design, dressed with fresh white roses, ranunculus and gold leaf sprigs, topped with a gold wire \"Engaged\" script and heart.',17000.00,'Whole cake','1 tier, about 2.0 kg','docs/cakes/Engagement/EngagementCake4.jpeg',1,NULL,'engagement','2026-10-17 05:21:55'),(89,'Just Engaged Blush Watercolor Cake','An ivory cake with soft pink watercolor brushstrokes and gold leaf flakes, decorated with blush and ivory roses, gold spheres, a couple\'s silhouette topper, gold interlocking rings, and a \"Just Engaged\" script plaque.',15400.00,'Whole cake','1 tier, about 2.0 kg','docs/cakes/Engagement/EngagementCake5.jpeg',1,NULL,'engagement','2026-10-17 05:21:55'),(90,'Gold Crack Pearl Engagement Cake','An ivory cake with a scattered pearl design and a hand-painted gold crack-effect line, topped with gold interlocking wedding-ring and diamond toppers.',17400.00,'Whole cake','1 tier, about 2.0 kg','docs/cakes/Engagement/EngagementCake6.jpeg',1,NULL,'engagement','2026-10-17 05:21:55'),(91,'Pearl Rose Ring Engagement Cake','An ivory cake with a scattered pearl finish and fresh white roses, topped with a gold interlocking rings-and-diamond topper, presented on a dark stand with sheer draping.',18100.00,'Whole cake','1 tier, about 2.0 kg','docs/cakes/Engagement/EngagementCake7.jpeg',1,NULL,'engagement','2026-10-17 05:21:55'),(92,'Onyx & Gold Graduate\'s Triumph Cake','A tall two-tone cake finished in nude and black fondant with a hand-painted gold leaf drip border. Topped with a black-and-gold fondant graduation cap and a rolled diploma tied with a gold bow. \"Congrats Ayah\" is spelled out in gold letters on the base board.',11800.00,'Whole cake','1.5 kg, round, fondant','docs/cakes/Graduation/GraduationCake1.jpeg',1,NULL,'graduation','2026-10-17 05:21:55'),(93,'Gilded Class of 2025 Celebration Cake','A tall ivory buttercream cake dusted with edible gold leaf flakes and finished with a cluster of black-and-gold sugar pearls. Crowned with a black fondant graduation cap with gold tassel and a rolled diploma tied in gold ribbon, alongside a \"Class of 2025\" banner.',10600.00,'Whole cake','1.5 kg, round','docs/cakes/Graduation/GraduationCake2.jpeg',1,NULL,'graduation','2026-10-17 05:21:55'),(94,'Zahraa\'s Gilded Achievement Cake','A crisp white buttercream cake with cascading garlands of black, white and gold sugar pearls down both sides. Topped with a laser-cut gold \"Class 2023\" plaque, a black fondant cap with gold tassel, and the graduate\'s name, \"Zahraa\", in gold letters.',9400.00,'Whole cake','1.2 kg, round','docs/cakes/Graduation/GraduationCake3.jpeg',1,NULL,'graduation','2026-10-17 05:21:55'),(95,'Pearl Elegance Graduation Cake','An elegant white buttercream cake with a delicate scattered pearl-dot texture and a neat piped shell border at the base. Finished with a navy fondant graduation cap, gold tassel, and rolled diploma, presented on a \"Class Of 2026\" placard.',7400.00,'Whole cake','1.0 kg, round','docs/cakes/Graduation/GraduationCake4.jpeg',1,NULL,'graduation','2026-10-17 05:21:56'),(96,'Golden Rosette Congratulations Cake','A two-tier ivory buttercream cake with lush piped rosette borders studded with gold sugar pearls. A gold script \"Congrats\" topper sits above a glitter-black graduation cap on the top tier, with a diploma cutout decorating the lower tier.',12500.00,'Whole cake','2-tier, about 2.0 kg','docs/cakes/Graduation/GraduationCake5.jpeg',1,NULL,'graduation','2026-10-17 05:21:56'),(97,'She Did It! Rustic Rose Graduate Cake','A rustic-elegant ivory buttercream cake dressed with blush and cream sugar roses and gold leaf accents. Features a silhouette of a graduate cheering with her diploma raised, a \"She Did It!\" script message, and a black fondant cap with gold tassel and diploma corsage on top.',10600.00,'Whole cake','1.5 kg, rustic','docs/cakes/Graduation/GraduationCake6.jpeg',1,NULL,'graduation','2026-10-17 05:21:56'),(98,'Sunshine Rosette Number 5 Cake','A number-5-shaped sponge cake generously piped with yellow, peach and white buttercream rosettes and star swirls.',7100.00,'Number cake','Single digit, about 0.9 kg','docs/cakes/Number Cakes/Numbercake1.jpeg',1,NULL,'number','2026-10-17 05:21:56'),(99,'Emerald Macaron Number 8 Cake','A chocolate number-8 cake piped with green and ivory buttercream swirls, decorated with macarons, gold pearls and wrapped chocolates, alongside a \"Happy Birthday\" plaque.',9000.00,'Number cake','Single digit, about 1.0 kg','docs/cakes/Number Cakes/Numbercake2.jpeg',1,NULL,'number','2026-10-17 05:21:56'),(100,'Berry Bliss Number 20 Cake','A red velvet number-20 cake with dollops of cream cheese frosting piped between the layers, topped generously with fresh strawberries, blackberries, raspberries and blueberries.',11700.00,'Number cake','Double digit, about 1.4 kg','docs/cakes/Number Cakes/Numbercake3.jpeg',1,NULL,'number','2026-10-17 05:21:56'),(101,'Lea\'s Strawberry Meringue Number 19 Cake','A vanilla number cake layered with cream, fresh strawberries, meringue kisses and pink macarons, personalized with a \"Lea\" cookie plaque.',11500.00,'Number cake','Double digit, about 1.4 kg','docs/cakes/Number Cakes/Numbercake4.jpeg',1,NULL,'number','2026-10-17 05:21:56'),(102,'Floral Meringue Number 20 Cake','A cookie-based number-20 cake decorated with meringue kisses, pink macarons, fresh cherries, purple dahlias and popcorn and chocolate-bar accents.',11800.00,'Number cake','Double digit, about 1.4 kg','docs/cakes/Number Cakes/Numbercake5.jpeg',1,NULL,'number','2026-10-17 05:21:56'),(103,'Pastel Dream Number 1 Cake','A pastel pink and lilac number-1 cake decorated with buttercream roses, meringues, candy pieces, and fondant hearts and stars, personalized with the name \"Миланочке\" in pink letters on the board.',7400.00,'Number cake','Single digit, about 0.9 kg','docs/cakes/Number Cakes/Numbercake6.jpeg',1,NULL,'number','2026-10-17 05:21:56'),(104,'Golden Butterfly Number 40 Cake','A number-40 cake piped with dusty-blue and ivory buttercream rosettes, finished with gold leaf accents and delicate gold wire butterflies.',11200.00,'Number cake','Double digit, about 1.4 kg','docs/cakes/Number Cakes/Numbercake7.jpeg',1,NULL,'number','2026-10-17 05:21:56'),(105,'Rainbow Delight Bite Squares','A vibrant rainbow-layered sponge cake cut into bite-size squares, frosted with white buttercream and finished with a scatter of rainbow sprinkles.',7700.00,'Box of 24','24 bite squares','docs/cakes/Party/Rainbow cake1.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(106,'Classic Ruby Red Velvet Cake','A classic round red velvet cake with a swirled cream cheese frosting crown on top and red velvet crumb coating the sides, presented on a gold cake board.',5300.00,'Whole cake','About 0.75 kg, round','docs/cakes/Party/Red Velvet Cake1.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(107,'Red Velvet Rosette & Chocolate Bark Cake','A layered red velvet cake finished with ivory cream cheese frosting, topped with piped rosettes and shards of red and white chocolate bark.',10700.00,'Whole cake','1.2 kg, round','docs/cakes/Party/Red Velvet Cake2.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(108,'Pandan Ribbon Layer Cake','A Sri Lankan-style ribbon layer cake featuring a bright green pandan sponge layer atop a golden butter cake base, finished with a white chocolate glaze and a crunchy nut topping..',8300.00,'Whole cake','1.5 kg, ribbon layer','docs/cakes/Party/Ribbon Cake1.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(109,'Crimson Rose Ombre Wedding Cake','An elegant three-tier cake fading from ivory to a deep red ombré fondant finish, cascading with lush handcrafted deep-red sugar roses and leaves, crowned with a full rose bouquet topper.',41500.00,'Whole cake','3-tier, about 4.5 kg','docs/cakes/Wedding/WeddingCake1.jpeg',1,NULL,'wedding','2026-10-17 05:21:56'),(110,'Grand Garden Rose Wedding Cake','A grand four-tier textured buttercream wedding cake displayed on an ornate gold pedestal stand, dressed with cascading fresh blush and ivory garden roses, ranunculus, and trailing eucalyptus greenery.',87500.00,'Whole cake','4-tier, about 9 kg','docs/cakes/Wedding/WeddingCake2.jpeg',1,NULL,'wedding','2026-10-17 05:21:56'),(111,'J&N Pearl & Rose Wedding Cake','A classic three-tier white fondant cake finished with delicate pearl-dot piping, clusters of fresh white roses and baby\'s breath, and a gold script \"J&N\" monogram topper.',66500.00,'Whole cake','4-tier, about 8 kg','docs/cakes/Wedding/WeddingCake3.jpeg',1,NULL,'wedding','2026-10-17 05:21:56'),(112,'N&S Rose Gold Champagne Wedding Cake','A two-tier ivory buttercream cake with a fine pearl \"champagne bubble\" texture, accented with blush and rose-gold roses and hydrangea, and a rose-gold \"N&S\" monogram topper.',38500.00,'Whole cake','3-tier, about 4.5 kg','docs/cakes/Wedding/WeddingCake4.jpeg',1,NULL,'wedding','2026-10-17 05:21:56'),(113,'Draped Curtain Baby\'s Breath Wedding Cake','A modern two-tier cake wrapped in a draped, fringed fondant \"curtain\" texture, each tier crowned with a full ring of fresh baby\'s breath.',32500.00,'Whole cake','3-tier, about 4.5 kg','docs/cakes/Wedding/WeddingCake5.jpeg',1,NULL,'wedding','2026-10-17 05:21:56'),(114,'Bridal Lace Applique Wedding Cake','A three-tier ivory fondant cake hand-piped with intricate lace-inspired floral appliqué and clusters of pearls, evoking the look of a bridal lace gown.',59000.00,'Whole cake','4-tier, about 7 kg','docs/cakes/Wedding/WeddingCake6.jpeg',1,NULL,'wedding','2026-10-17 05:21:56'),(115,'S&M Burgundy Rose Wedding Cake','A two-tier ivory buttercream cake scattered with pearls and deep burgundy rose clusters, finished with a gold script \"S&M\" monogram and displayed on a silver-edged cake stand.',34000.00,'Whole cake','3-tier, about 4.5 kg','docs/cakes/Wedding/WeddingCake7.jpeg',1,NULL,'wedding','2026-10-17 05:21:56'),(116,'Cascading Rose Grand Wedding Cake','A four-tier white buttercream wedding cake trimmed with a cascade of blush and ivory garden roses flowing down one side.',83500.00,'Whole cake','5-tier, about 9 kg','docs/cakes/Wedding/WeddingCake8.jpg',1,NULL,'wedding','2026-10-17 05:21:56'),(117,'Draped Sash Pearl Wedding Cake','A four-tier ivory pearl-textured cake wrapped in a dramatic draped fondant \"sash\" that swirls from top to bottom, accented with white roses and baby\'s breath.',81000.00,'Whole cake','5-tier, about 10 kg','docs/cakes/Wedding/WeddingCake9.jpeg',1,NULL,'wedding','2026-10-17 05:21:56'),(118,'Y&M Pearl Cascade Wedding Cake','A four-tier white cake finished with a fine pearl-sugar texture, cascading white roses and baby\'s breath, and a gold script \"Y&M\" monogram topper.',77500.00,'Whole cake','5-tier, about 9 kg','docs/cakes/Wedding/WeddingCake10.jpeg',1,NULL,'wedding','2026-10-17 05:21:56'),(119,'Cinnamon Apple Spiral Cake','A rustic apple cake arranged with a spiral of thin apple slices baked into the top, dusted with cinnamon sugar.',4900.00,'Whole cake','1.0 kg, spiral','docs/cakes/Others/Apple Cake1.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(120,'Rustic Banana Walnut Loaf','A rustic banana loaf cake topped generously with chopped walnuts.',3000.00,'Loaf','1 loaf, about 0.7 kg','docs/cakes/Others/Banana Cake1.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(121,'Golden Crosshatch Butter Cake','A classic golden butter cake baked in a square tin with a signature crosshatch-scored top.',3100.00,'Whole cake','About 0.8 kg','docs/cakes/Others/butter cake.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(122,'Classic Coffee Rosette Cake','A layered coffee cake frosted in coffee buttercream, piped with rosette swirls and finished with chocolate coffee beans.',5800.00,'Whole cake','1.0 kg, round','docs/cakes/Others/Coffee Cake1.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(123,'Classic Date & Walnut Delight Cake','A round date cake topped with swirls of caramel buttercream and walnut pieces, served alongside fresh dates and walnuts.',5900.00,'Whole cake','1.0 kg, round','docs/cakes/Others/date and Walnut Cake1.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(124,'Caramel Date & Walnut Cake','A rustic date cake topped with a caramel-date glaze and crushed walnuts.',6000.00,'Whole cake','1.0 kg, round','docs/cakes/Others/Date Cake1.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(125,'Date & Walnut Caramel Loaf','A loaf-style date cake glazed with caramel and topped generously with walnut halves.',3500.00,'Loaf','1 loaf, about 0.7 kg','docs/cakes/Others/Date Cake2.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(126,'Tropical Medley Fresh Fruit Cake','A cream cake with a chocolate ganache drip, generously topped with an assortment of fresh fruit including strawberries, kiwi, blueberries, raspberries, blackberries and orange segments.',10300.00,'Whole cake','1.5 kg, round','docs/cakes/Others/Fruit Cake4.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(127,'Tropical Fruit & Almond Gateau','A cream-frosted gateau topped with a colorful medley of fresh tropical fruit — mango, kiwi and grapes — and a toasted almond flake border.',8800.00,'Whole cake','1.2 kg, round','docs/cakes/Others/Gateaus1.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(128,'Molten Chocolate Lava Cake','An individual warm chocolate lava cake with a molten chocolate center, dusted with powdered sugar.',5900.00,'Box of 6','6 individual lava cakes','docs/cakes/Others/Lava Cake1.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(129,'Zesty Lemon Bundt Cake','A bundt-style lemon cake glazed with lemon icing and garnished with fresh lemon slices.',4300.00,'Whole cake','About 0.9 kg bundt','docs/cakes/Others/Lemon Cake1.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(130,'Lemon Zest Rosette Layer Cake','A round layer cake frosted in pale-yellow buttercream, piped with rosette swirls and topped with lemon zest.',5500.00,'Whole cake','1.0 kg, layer cake','docs/cakes/Others/Lemon Cake2.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(131,'Classic Golden Sponge Cake','A classic plain golden sponge cake with a simple, airy crumb.',2800.00,'Whole cake','About 0.8 kg','docs/cakes/Others/Sponge Cake1.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(132,'Chocolate Ganache Swiss Roll','A chocolate Swiss roll filled with vanilla cream, coated in chocolate ganache and topped with chocolate shards.',3800.00,'Roll','1 roll, about 0.6 kg','docs/cakes/Others/Swiss Roll1.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(133,'Strawberry Cream Swiss Roll','A vanilla Swiss roll filled with strawberry cream and jam, coated in whipped cream and topped with fresh strawberries.',3700.00,'Roll','1 roll, about 0.6 kg','docs/cakes/Others/Swiss Roll2.jpeg',1,NULL,'New','2026-10-17 05:21:56');
/*!40000 ALTER TABLE `cake` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cake_backup_20261025`
--

DROP TABLE IF EXISTS `cake_backup_20261025`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cake_backup_20261025` (
  `cake_id` int(11) NOT NULL DEFAULT 0,
  `cake_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `image` varchar(255) DEFAULT NULL COMMENT 'File path or external URL',
  `availability` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = available, 0 = unavailable',
  `stock_quantity` int(11) DEFAULT NULL,
  `badge` varchar(50) DEFAULT NULL COMMENT 'e.g. Bestseller, New, Seasonal, Kids, 21st',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cake_backup_20261025`
--

LOCK TABLES `cake_backup_20261025` WRITE;
/*!40000 ALTER TABLE `cake_backup_20261025` DISABLE KEYS */;
INSERT INTO `cake_backup_20261025` VALUES (1,'Golden Pearl Anniversary Cake','A tall ivory buttercream cake scattered with cascading gold sugar pearls and flanked by deep red rose clusters, topped with a gold script \"Happy Anniversary\" topper.',10500.00,'docs/cakes/Anniversary/Anniversary cake1.jpeg',1,NULL,'Bestseller','2026-10-04 02:07:19'),(2,'Burgundy Ombre Heart Anniversary Cake','A two-tone burgundy-to-ivory ombré buttercream cake finished with a jagged gold-leaf border and gold sugar pearls, accented with double gold wire hearts and a black \"Happy Anniversary\" topper.',9000.00,'docs/cakes/Anniversary/Anniversary cake2.jpeg',1,NULL,'anniversary','2026-10-04 02:07:19'),(3,'Magenta Hydrangea Anniversary Cake','An ivory buttercream cake with fine wave piping and scattered pearls, decorated with magenta hydrangea blooms and gold spheres, finished with a gold script \"Wedding Anniversary\" topper.',10000.00,'docs/cakes/Anniversary/Anniversary cake3.jpeg',1,NULL,'anniversary','2026-10-04 02:07:19'),(4,'Together Forever Gold Cascade Cake','An ivory buttercream cake finished with a dramatic gold-leaf cascade and fresh red and white roses with eucalyptus. Topped with a gold double-heart topper and \"Happy Anniversary\" script, with \"Together Forever\" written on the base board.',10500.00,'docs/cakes/Anniversary/Anniversary cake4.jpeg',1,NULL,'anniversary','2026-10-04 02:07:19'),(5,'Golden Drip Anniversary Cake','A white buttercream cake with a dripped, melting-style texture, scattered gold leaf and sugar pearls, and red fondant hearts around the sides. Finished with a mirrored \"Happy Anniversary\" topper and \"MOM DAD\" lettering on the base',8000.00,'docs/cakes/Anniversary/Anniversary cake5.jpeg',1,NULL,'Seasonal','2026-10-04 02:07:19'),(6,'Modern MuseRed Anniversary Cake','A white cake with a hand-painted red ombré and scattered sugar pearls, decorated with red heart outlines, a fresh red rose and \"Happy Anniversary\" piped in red script',8000.00,'docs/cakes/Anniversary/Anniversary cake6.jpeg',1,NULL,'New','2026-10-04 02:07:19'),(7,'SpongeBob Kids Birthday Cake','A yellow fondant cake styled as SpongeBob\'s face with a cut-out \"cheese hole\" texture, topped with SpongeBob and friends cutout toppers and a birthday age plaque.',7000.00,'docs/cakes/Birthday/Kids Birthday Cake1.jpeg',1,NULL,'Kids','2026-10-04 02:07:19'),(8,'Hello Kitty Kids Birthday Cake','A white and pink Hello Kitty themed cake with piped bows and polka dots, a Hello Kitty figure, a pink \"5\" numeral, and a \"Happy Birthday\" heart plaque.',6000.00,'docs/cakes/Birthday/Kids Birthday Cake2.jpeg',1,NULL,'Kids','2026-10-04 02:07:19'),(9,'Jungle Safari Adventure KidsBirthday Cake','A green jungle-themed cake with a painted leaf design, topped with fondant lion, monkey, giraffe and elephant figures, green balloons, and a \"2\" numeral.',7500.00,'docs/cakes/Birthday/Kids Birthday Cake3.jpeg',1,NULL,'Kids','2026-10-04 02:07:19'),(10,'Lucas\'s Dino Volcano Kids Adventure Birthday Cake','A light-blue dinosaur-themed cake with a fondant volcano, palm leaves and colorful dinosaur figures, personalized with a \"LUCAS 3\" wood-effect plaque.',8800.00,'docs/cakes/Birthday/Kids Birthday Cake4.jpeg',1,NULL,'Kids','2026-10-04 02:07:19'),(11,'Pastel Smiley Rainbow Kids Birthday Cake','A pink buttercream cake with a kawaii smiley face and blushed cheeks, finished with a shell border and fondant flower, rainbow, cloud and star toppers.',5000.00,'docs/cakes/Birthday/Kids Birthday Cake5.jpg',1,NULL,'Kids','2026-10-04 02:07:19'),(12,'Spider-Man Kids Birthday Cake','A bold red Spider-Man themed cake with a hand-painted web design, a 3D Spider-Man figure, a blue \"3\" numeral, and the name \"TARIK\" in blue fondant letters.',6500.00,'docs/cakes/Birthday/Kids Birthday Cake6.jpeg',1,NULL,'Kids','2026-10-04 02:07:19'),(13,'Safari Freinds Kids Birthday Cake','A buttercream cake styled with a grass texture and fondant zebra, elephant and giraffe cutouts, a smiling sun topper, pastel balloons, and a blue \"1\" numeral.',8700.00,'docs/cakes/Birthday/Kids Birthday Cake7.jpeg',1,NULL,'Kids','2026-10-04 02:07:19'),(14,'Golden 21st Birthday Cake','An ivory buttercream cake with a cascade of blush, white and gold sugar spheres, finished with a gold script \"Happy 21st Jess\" topper.',7500.00,'docs/cakes/Birthday/21st birthday cake1.jpeg',1,NULL,'21st','2026-10-04 02:07:19'),(15,'Black and White 21st Birthday Cake','A white buttercream cake with a piped lace border, small black fondant hearts, black ribbon bows, and \"twenty one\" written in black script.',6800.00,'docs/cakes/Birthday/21st birthday cake2.jpeg',1,NULL,'21st','2026-10-04 02:07:19'),(16,'Gold Drip 21st Birthday Cake','A matte black fondant cake with a gold-leaf paint-brush accent and a mirrored gold \"21\" topper with a drip effect, styled with lit candles.',8000.00,'docs/cakes/Birthday/21st birthday cake3.jpeg',1,NULL,'21st','2026-10-04 02:07:19'),(17,'Blush Rosette Mirror 21st Cake','A cake fully covered in blush-pink and ivory buttercream rosettes, finished with a small bow and a gold mirrored \"21\" topper.',9000.00,'docs/cakes/Birthday/21st birthday cake4.jpeg',1,NULL,'21st','2026-10-04 02:07:19'),(18,'Rustic Baby\'s Breath 21st Cake','An ivory textured buttercream cake decorated with dried baby\'s breath sprigs and a written \"21\" numeral, presented with a \"Happy 21st Birthday\" gift tag.',5800.00,'docs/cakes/Birthday/21st birthday cake5.jpeg',1,NULL,'21st','2026-10-04 02:07:19'),(19,'Disco Nights 21st Birthday Cake','A buttercream cake decorated with gold confetti sprinkles, gold spheres, a disco ball, and mini liquor-bottle cake toppers alongside double gold \"21\" numerals.',9000.00,'docs/cakes/Birthday/21st birthday cake6.jpeg',1,NULL,'21st','2026-10-04 02:07:19'),(20,'Golden Truffle 21st Coming-Up Cake','An ivory cake finished with a chocolate ganache drip, decorated generously with gold-wrapped chocolate truffles and wafer rolls, topped with a gold \"21st Coming Up\" script.',9800.00,'docs/cakes/Birthday/21st birthday cake7.jpeg',1,NULL,'21st','2026-10-04 02:07:19'),(59,'Classic Black Forest Cake','A classic Black Forest cake with dark chocolate sponge layers, whipped cream filling, and fresh cherries on top and around the base.',6500.00,'docs/cakes/Birthday/Black Forest Cake1.jpeg',1,NULL,'Classic','2026-10-17 05:21:55'),(60,'Cherry Chocolate Shavings Forest Cake','A chocolate sponge cake coated in whipped cream with dark chocolate shavings around the sides and a generous crown of fresh cherries on top.',6800.00,'docs/cakes/Birthday/Black Forest Cake2.jpeg',1,NULL,'Classic','2026-10-17 05:21:55'),(61,'Triple-Layer Chocolate Rosette Cake','A multi-layer chocolate cake filled and coated in chocolate buttercream, finished with piped rosette borders top and bottom.',6500.00,'docs/cakes/Birthday/Chocolate Cake1.jpeg',1,NULL,'birthday','2026-10-17 05:21:55'),(62,'Glossy Ganache Chocolate Cake','A chocolate cake with a glossy ganache drip, topped with piped chocolate buttercream swirls and chunks of dark chocolate.',6200.00,'docs/cakes/Birthday/Chocolate Cake2.jpeg',1,NULL,'birthday','2026-10-17 05:21:55'),(63,'Chocolate Truffle Decadence Cake','A chocolate cake with a thick ganache drip, topped with chocolate truffle balls and chocolate curls.',6500.00,'docs/cakes/Birthday/Chocolate Cake3.jpeg',1,NULL,'birthday','2026-10-17 05:21:55'),(64,'Rustic Naked Chocolate Cake','A rustic \"naked style\" chocolate layer cake with a thin chocolate frosting between layers and a light dusting of chocolate shavings on top.',5800.00,'docs/cakes/Birthday/Chocolate Cake4.jpeg',1,NULL,'birthday','2026-10-17 05:21:55'),(65,'Golden Peach Rose 90th Birthday Cake','An ivory textured buttercream cake with a gold-leaf drizzle accent, fresh peach roses and baby\'s breath, topped with a gold \"90\" numeral.',7500.00,'docs/cakes/Birthday/Old Birthday Cake1.jpeg',1,NULL,'Old','2026-10-17 05:21:55'),(66,'Golden Balloon 50th Birthday Cake','An ivory cake topped with a dramatic cluster of gold balloon-style spheres and gold bead drips, finished with a gold \"50\" numeral and paired with a champagne bottle.',7400.00,'docs/cakes/Birthday/Old Birthday Cake2.jpeg',1,NULL,'Old','2026-10-17 05:21:55'),(67,'Starlight Gold 80th Birthday Cake','A white cake with a chocolate ganache drip and swirled buttercream dollops, topped with a gold \"80\" numeral and black-and-gold star picks.',8200.00,'docs/cakes/Birthday/Old Birthday Cake3.jpeg',1,NULL,'Old','2026-10-17 05:21:55'),(68,'Fresh Strawberry Rosette Cake','A pale pink cake finished with piped rosette swirls and a ring of fresh whole and halved strawberries on top.',6800.00,'docs/cakes/Birthday/Strawberry Cake1.jpeg',1,NULL,'strawberry','2026-10-17 05:21:55'),(69,'Strawberry Sprinkle Delight Cake','A pink buttercream cake with swirled rosette piping and red sprinkle-dot accents, topped generously with fresh strawberries.',9200.00,'docs/cakes/Birthday/Strawberry Cake2.jpeg',1,NULL,'strawberry','2026-10-17 05:21:55'),(70,'Traditional Glazed Christmas Fruit Cake','A traditional dark fruit cake topped with a glossy glazed arrangement of dried and glacé fruit and nuts.',7500.00,'docs/cakes/Christmas/Christmas Cake1.jpeg',1,NULL,'christmas','2026-10-17 05:21:55'),(71,'Festive Wreath Christmas Cake','An ivory buttercream cake finished with a chocolate ganache drip, crowned with a festive green buttercream wreath studded with red, gold and silver sugar baubles.',7000.00,'docs/cakes/Christmas/Christmas Cake2.jpeg',1,NULL,'christmas','2026-10-17 05:21:55'),(72,'Buche de Noel Yule Log','A chocolate Yule log (Bûche de Noël) — a rolled sponge finished with a rich chocolate ganache bark texture, garnished with fresh cranberries and rosemary sprigs and dusted with powdered \"snow\" sugar.',7500.00,'docs/cakes/Christmas/Christmas Cake3.jpeg',1,NULL,'christmas','2026-10-17 05:21:55'),(73,'Snowflake & Holly Christmas Cake','An ivory buttercream cake with delicate snowflake piping on the sides, topped with a hand-piped holly wreath and a red bow.',6800.00,'docs/cakes/Christmas/Christmas Cake4.jpeg',1,NULL,'christmas','2026-10-17 05:21:55'),(74,'Golden Crust Traditional Fruit Cake','A classic baked fruit cake with a deep golden-brown crust and mixed dried fruit studded throughout.',7000.00,'docs/cakes/Christmas/Fruit Cake1.jpeg',1,NULL,'christmas','2026-10-17 05:21:55'),(75,'Blush Rose Swirl Cupcakes','Vanilla celebration cake with a custom graduation message.',2800.00,'docs/cakes/Cupcake/CupCakes1.jpeg',1,NULL,'New','2026-10-17 05:21:55'),(76,'Navy & Gold Wave Cupcakes','Red velvet cake with cream cheese frosting and gold accents.',3000.00,'docs/cakes/Cupcake/CupCakes2.jpeg',1,NULL,'New','2026-10-17 05:21:55'),(77,'Dusty Blue Rosette Cupcakes','Vanilla number cake with fresh cream, berries and macarons.',3000.00,'docs/cakes/Cupcake/CupCakes3.jpeg',1,NULL,'New','2026-10-17 05:21:55'),(78,'Pink Mauve Red Velvet Cupcakes','Chocolate number cake with ganache and chocolate shards.',3200.00,'docs/cakes/Cupcake/CupCakes4.jpeg',1,NULL,'New','2026-10-17 05:21:55'),(79,'Sparkling Pink Rose Cupcakes','Strawberry number cake with cream rosettes and fresh fruit.',3000.00,'docs/cakes/Cupcake/CupCakes5.jpeg',1,NULL,'New','2026-10-17 05:21:55'),(80,'Mocha & Ivory Rose Cupcakes','Red velvet number cake with cream cheese frosting and pearls.',3200.00,'docs/cakes/Cupcake/CupCakes6.jpeg',1,NULL,'New','2026-10-17 05:21:55'),(81,'Ivory Pearl Wedding Ring Cupcakes','Vanilla berry number cake with a bright celebration finish.',2800.00,'docs/cakes/Cupcake/CupCakes7.jpeg',1,NULL,'New','2026-10-17 05:21:55'),(82,'Burgundy Rose Half-Dozen Cupcakes','Funfetti sponge with vanilla buttercream and colourful confetti.',4000.00,'docs/cakes/Cupcake/CupCakes8.jpeg',1,NULL,'New','2026-10-17 05:21:55'),(83,'Ivory Rose Wedding Cupcake Tower','Coconut and pineapple cake with bright tropical decorations.',7200.00,'docs/cakes/Cupcake/WeddingCupCakes1.jpeg',1,NULL,'New','2026-10-17 05:21:55'),(84,'Gilded Ivory Rose Wedding Cupcakes','Chocolate cake with playful decorations for a party table.',5000.00,'docs/cakes/Cupcake/WeddingCupCakes2.jpeg',1,NULL,'New','2026-10-17 05:21:55'),(85,'Golden Rings Engagement Cake','A two-tier ivory buttercream cake with a soft wave-textured finish and scattered pearls, topped with a gold wire \"engagement rings\" topper and gold script signage.',19000.00,'docs/cakes/Engagement/EngagementCake1.jpeg',1,NULL,'engagement','2026-10-17 05:21:55'),(86,'Draped Fringe Engagement Cake','A two-tier ivory cake with a dramatic draped fondant fringe swirling diagonally across both tiers, softened with sprays of baby\'s breath.',18500.00,'docs/cakes/Engagement/EngagementCake2.jpeg',1,NULL,'engagement','2026-10-17 05:21:55'),(87,'S&H Champagne Bubble Engagement Cake','A two-tier ivory cake with a pearl \"champagne bubble\" texture, a gold \"S&H\" monogram, and a gold interlocking rings-and-diamond topper.',19500.00,'docs/cakes/Engagement/EngagementCake3.jpeg',1,NULL,'engagement','2026-10-17 05:21:55'),(88,'Engaged in Ivory Rose Cake','A single-tier ivory cake with a soft pearl cluster design, dressed with fresh white roses, ranunculus and gold leaf sprigs, topped with a gold wire \"Engaged\" script and heart.',18500.00,'docs/cakes/Engagement/EngagementCake4.jpeg',1,NULL,'engagement','2026-10-17 05:21:55'),(89,'Just Engaged Blush Watercolor Cake','An ivory cake with soft pink watercolor brushstrokes and gold leaf flakes, decorated with blush and ivory roses, gold spheres, a couple\'s silhouette topper, gold interlocking rings, and a \"Just Engaged\" script plaque.',17500.00,'docs/cakes/Engagement/EngagementCake5.jpeg',1,NULL,'engagement','2026-10-17 05:21:55'),(90,'Gold Crack Pearl Engagement Cake','An ivory cake with a scattered pearl design and a hand-painted gold crack-effect line, topped with gold interlocking wedding-ring and diamond toppers.',19500.00,'docs/cakes/Engagement/EngagementCake6.jpeg',1,NULL,'engagement','2026-10-17 05:21:55'),(91,'Pearl Rose Ring Engagement Cake','An ivory cake with a scattered pearl finish and fresh white roses, topped with a gold interlocking rings-and-diamond topper, presented on a dark stand with sheer draping.',21000.00,'docs/cakes/Engagement/EngagementCake7.jpeg',1,NULL,'engagement','2026-10-17 05:21:55'),(92,'Onyx & Gold Graduate\'s Triumph Cake','A tall two-tone cake finished in nude and black fondant with a hand-painted gold leaf drip border. Topped with a black-and-gold fondant graduation cap and a rolled diploma tied with a gold bow. \"Congrats Ayah\" is spelled out in gold letters on the base board.',11500.00,'docs/cakes/Graduation/GraduationCake1.jpeg',1,NULL,'graduation','2026-10-17 05:21:55'),(93,'Gilded Class of 2025 Celebration Cake','A tall ivory buttercream cake dusted with edible gold leaf flakes and finished with a cluster of black-and-gold sugar pearls. Crowned with a black fondant graduation cap with gold tassel and a rolled diploma tied in gold ribbon, alongside a \"Class of 2025\" banner.',11000.00,'docs/cakes/Graduation/GraduationCake2.jpeg',1,NULL,'graduation','2026-10-17 05:21:55'),(94,'Zahraa\'s Gilded Achievement Cake','A crisp white buttercream cake with cascading garlands of black, white and gold sugar pearls down both sides. Topped with a laser-cut gold \"Class 2023\" plaque, a black fondant cap with gold tassel, and the graduate\'s name, \"Zahraa\", in gold letters.',9800.00,'docs/cakes/Graduation/GraduationCake3.jpeg',1,NULL,'graduation','2026-10-17 05:21:55'),(95,'Pearl Elegance Graduation Cake','An elegant white buttercream cake with a delicate scattered pearl-dot texture and a neat piped shell border at the base. Finished with a navy fondant graduation cap, gold tassel, and rolled diploma, presented on a \"Class Of 2026\" placard.',8790.00,'docs/cakes/Graduation/GraduationCake4.jpeg',1,NULL,'graduation','2026-10-17 05:21:56'),(96,'Golden Rosette Congratulations Cake','A two-tier ivory buttercream cake with lush piped rosette borders studded with gold sugar pearls. A gold script \"Congrats\" topper sits above a glitter-black graduation cap on the top tier, with a diploma cutout decorating the lower tier.',8000.00,'docs/cakes/Graduation/GraduationCake5.jpeg',1,NULL,'graduation','2026-10-17 05:21:56'),(97,'She Did It! Rustic Rose Graduate Cake','A rustic-elegant ivory buttercream cake dressed with blush and cream sugar roses and gold leaf accents. Features a silhouette of a graduate cheering with her diploma raised, a \"She Did It!\" script message, and a black fondant cap with gold tassel and diploma corsage on top.',9900.00,'docs/cakes/Graduation/GraduationCake6.jpeg',1,NULL,'graduation','2026-10-17 05:21:56'),(98,'Sunshine Rosette Number 5 Cake','A number-5-shaped sponge cake generously piped with yellow, peach and white buttercream rosettes and star swirls.',5500.00,'docs/cakes/Number Cakes/Numbercake1.jpeg',1,NULL,'number','2026-10-17 05:21:56'),(99,'Emerald Macaron Number 8 Cake','A chocolate number-8 cake piped with green and ivory buttercream swirls, decorated with macarons, gold pearls and wrapped chocolates, alongside a \"Happy Birthday\" plaque.',6000.00,'docs/cakes/Number Cakes/Numbercake2.jpeg',1,NULL,'number','2026-10-17 05:21:56'),(100,'Berry Bliss Number 20 Cake','A red velvet number-20 cake with dollops of cream cheese frosting piped between the layers, topped generously with fresh strawberries, blackberries, raspberries and blueberries.',4500.00,'docs/cakes/Number Cakes/Numbercake3.jpeg',1,NULL,'number','2026-10-17 05:21:56'),(101,'Lea\'s Strawberry Meringue Number 19 Cake','A vanilla number cake layered with cream, fresh strawberries, meringue kisses and pink macarons, personalized with a \"Lea\" cookie plaque.',4800.00,'docs/cakes/Number Cakes/Numbercake4.jpeg',1,NULL,'number','2026-10-17 05:21:56'),(102,'Floral Meringue Number 20 Cake','A cookie-based number-20 cake decorated with meringue kisses, pink macarons, fresh cherries, purple dahlias and popcorn and chocolate-bar accents.',7900.00,'docs/cakes/Number Cakes/Numbercake5.jpeg',1,NULL,'number','2026-10-17 05:21:56'),(103,'Pastel Dream Number 1 Cake','A pastel pink and lilac number-1 cake decorated with buttercream roses, meringues, candy pieces, and fondant hearts and stars, personalized with the name \"Миланочке\" in pink letters on the board.',4900.00,'docs/cakes/Number Cakes/Numbercake6.jpeg',1,NULL,'number','2026-10-17 05:21:56'),(104,'Golden Butterfly Number 40 Cake','A number-40 cake piped with dusty-blue and ivory buttercream rosettes, finished with gold leaf accents and delicate gold wire butterflies.',5800.00,'docs/cakes/Number Cakes/Numbercake7.jpeg',1,NULL,'number','2026-10-17 05:21:56'),(105,'Rainbow Delight Bite Squares','A vibrant rainbow-layered sponge cake cut into bite-size squares, frosted with white buttercream and finished with a scatter of rainbow sprinkles.',6500.00,'docs/cakes/Party/Rainbow cake1.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(106,'Classic Ruby Red Velvet Cake','A classic round red velvet cake with a swirled cream cheese frosting crown on top and red velvet crumb coating the sides, presented on a gold cake board.',3500.00,'docs/cakes/Party/Red Velvet Cake1.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(107,'Red Velvet Rosette & Chocolate Bark Cake','A layered red velvet cake finished with ivory cream cheese frosting, topped with piped rosettes and shards of red and white chocolate bark.',7900.00,'docs/cakes/Party/Red Velvet Cake2.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(108,'Pandan Ribbon Layer Cake','A Sri Lankan-style ribbon layer cake featuring a bright green pandan sponge layer atop a golden butter cake base, finished with a white chocolate glaze and a crunchy nut topping..',8200.00,'docs/cakes/Party/Ribbon Cake1.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(109,'Crimson Rose Ombre Wedding Cake','An elegant three-tier cake fading from ivory to a deep red ombré fondant finish, cascading with lush handcrafted deep-red sugar roses and leaves, crowned with a full rose bouquet topper.',42000.00,'docs/cakes/Wedding/WeddingCake1.jpeg',1,NULL,'wedding','2026-10-17 05:21:56'),(110,'Grand Garden Rose Wedding Cake','A grand four-tier textured buttercream wedding cake displayed on an ornate gold pedestal stand, dressed with cascading fresh blush and ivory garden roses, ranunculus, and trailing eucalyptus greenery.',78000.00,'docs/cakes/Wedding/WeddingCake2.jpeg',1,NULL,'wedding','2026-10-17 05:21:56'),(111,'J&N Pearl & Rose Wedding Cake','A classic three-tier white fondant cake finished with delicate pearl-dot piping, clusters of fresh white roses and baby\'s breath, and a gold script \"J&N\" monogram topper.',58000.00,'docs/cakes/Wedding/WeddingCake3.jpeg',1,NULL,'wedding','2026-10-17 05:21:56'),(112,'N&S Rose Gold Champagne Wedding Cake','A two-tier ivory buttercream cake with a fine pearl \"champagne bubble\" texture, accented with blush and rose-gold roses and hydrangea, and a rose-gold \"N&S\" monogram topper.',34000.00,'docs/cakes/Wedding/WeddingCake4.jpeg',1,NULL,'wedding','2026-10-17 05:21:56'),(113,'Draped Curtain Baby\'s Breath Wedding Cake','A modern two-tier cake wrapped in a draped, fringed fondant \"curtain\" texture, each tier crowned with a full ring of fresh baby\'s breath.',29000.00,'docs/cakes/Wedding/WeddingCake5.jpeg',1,NULL,'wedding','2026-10-17 05:21:56'),(114,'Bridal Lace Applique Wedding Cake','A three-tier ivory fondant cake hand-piped with intricate lace-inspired floral appliqué and clusters of pearls, evoking the look of a bridal lace gown.',50000.00,'docs/cakes/Wedding/WeddingCake6.jpeg',1,NULL,'wedding','2026-10-17 05:21:56'),(115,'S&M Burgundy Rose Wedding Cake','A two-tier ivory buttercream cake scattered with pearls and deep burgundy rose clusters, finished with a gold script \"S&M\" monogram and displayed on a silver-edged cake stand.',30000.00,'docs/cakes/Wedding/WeddingCake7.jpeg',1,NULL,'wedding','2026-10-17 05:21:56'),(116,'Cascading Rose Grand Wedding Cake','A four-tier white buttercream wedding cake trimmed with a cascade of blush and ivory garden roses flowing down one side.',70000.00,'docs/cakes/Wedding/WeddingCake8.jpg',1,NULL,'wedding','2026-10-17 05:21:56'),(117,'Draped Sash Pearl Wedding Cake','A four-tier ivory pearl-textured cake wrapped in a dramatic draped fondant \"sash\" that swirls from top to bottom, accented with white roses and baby\'s breath.',75000.00,'docs/cakes/Wedding/WeddingCake9.jpeg',1,NULL,'wedding','2026-10-17 05:21:56'),(118,'Y&M Pearl Cascade Wedding Cake','A four-tier white cake finished with a fine pearl-sugar texture, cascading white roses and baby\'s breath, and a gold script \"Y&M\" monogram topper.',70000.00,'docs/cakes/Wedding/WeddingCake10.jpeg',1,NULL,'wedding','2026-10-17 05:21:56'),(119,'Cinnamon Apple Spiral Cake','A rustic apple cake arranged with a spiral of thin apple slices baked into the top, dusted with cinnamon sugar.',3800.00,'docs/cakes/Others/Apple Cake1.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(120,'Rustic Banana Walnut Loaf','A rustic banana loaf cake topped generously with chopped walnuts.',3200.00,'docs/cakes/Others/Banana Cake1.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(121,'Golden Crosshatch Butter Cake','A classic golden butter cake baked in a square tin with a signature crosshatch-scored top.',3200.00,'docs/cakes/Others/butter cake.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(122,'Classic Coffee Rosette Cake','A layered coffee cake frosted in coffee buttercream, piped with rosette swirls and finished with chocolate coffee beans.',5800.00,'docs/cakes/Others/Coffee Cake1.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(123,'Classic Date & Walnut Delight Cake','A round date cake topped with swirls of caramel buttercream and walnut pieces, served alongside fresh dates and walnuts.',4800.00,'docs/cakes/Others/date and Walnut Cake1.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(124,'Caramel Date & Walnut Cake','A rustic date cake topped with a caramel-date glaze and crushed walnuts.',4800.00,'docs/cakes/Others/Date Cake1.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(125,'Date & Walnut Caramel Loaf','A loaf-style date cake glazed with caramel and topped generously with walnut halves.',3800.00,'docs/cakes/Others/Date Cake2.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(126,'Tropical Medley Fresh Fruit Cake','A cream cake with a chocolate ganache drip, generously topped with an assortment of fresh fruit including strawberries, kiwi, blueberries, raspberries, blackberries and orange segments.',7500.00,'docs/cakes/Others/Fruit Cake4.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(127,'Tropical Fruit & Almond Gateau','A cream-frosted gateau topped with a colorful medley of fresh tropical fruit — mango, kiwi and grapes — and a toasted almond flake border.',6800.00,'docs/cakes/Others/Gateaus1.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(128,'Molten Chocolate Lava Cake','An individual warm chocolate lava cake with a molten chocolate center, dusted with powdered sugar.',6500.00,'docs/cakes/Others/Lava Cake1.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(129,'Zesty Lemon Bundt Cake','A bundt-style lemon cake glazed with lemon icing and garnished with fresh lemon slices.',4200.00,'docs/cakes/Others/Lemon Cake1.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(130,'Lemon Zest Rosette Layer Cake','A round layer cake frosted in pale-yellow buttercream, piped with rosette swirls and topped with lemon zest.',4500.00,'docs/cakes/Others/Lemon Cake2.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(131,'Classic Golden Sponge Cake','A classic plain golden sponge cake with a simple, airy crumb.',2800.00,'docs/cakes/Others/Sponge Cake1.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(132,'Chocolate Ganache Swiss Roll','A chocolate Swiss roll filled with vanilla cream, coated in chocolate ganache and topped with chocolate shards.',3500.00,'docs/cakes/Others/Swiss Roll1.jpeg',1,NULL,'New','2026-10-17 05:21:56'),(133,'Strawberry Cream Swiss Roll','A vanilla Swiss roll filled with strawberry cream and jam, coated in whipped cream and topped with fresh strawberries.',3500.00,'docs/cakes/Others/Swiss Roll2.jpeg',1,NULL,'New','2026-10-17 05:21:56');
/*!40000 ALTER TABLE `cake_backup_20261025` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cake_category`
--

DROP TABLE IF EXISTS `cake_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cake_category` (
  `cake_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  PRIMARY KEY (`cake_id`,`category_id`),
  KEY `fk_cc_category` (`category_id`),
  CONSTRAINT `fk_cc_cake` FOREIGN KEY (`cake_id`) REFERENCES `cake` (`cake_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cc_category` FOREIGN KEY (`category_id`) REFERENCES `category` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Junction table: many-to-many between cake and category';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cake_category`
--

LOCK TABLES `cake_category` WRITE;
/*!40000 ALTER TABLE `cake_category` DISABLE KEYS */;
INSERT INTO `cake_category` VALUES (1,1),(2,1),(3,1),(4,1),(5,1),(6,1),(7,2),(7,8),(8,2),(8,8),(9,2),(9,8),(10,2),(10,8),(11,2),(11,8),(12,2),(12,8),(13,2),(13,8),(14,2),(15,2),(16,2),(17,2),(18,2),(19,2),(20,2),(59,2),(59,10),(60,2),(60,10),(61,2),(62,2),(63,2),(64,2),(65,2),(66,2),(67,2),(68,2),(69,2),(70,3),(71,3),(72,3),(73,3),(74,3),(75,4),(76,4),(77,4),(78,4),(79,4),(80,4),(81,4),(82,4),(83,4),(84,4),(85,5),(86,5),(87,5),(88,5),(89,5),(90,5),(91,5),(92,6),(92,8),(93,6),(93,8),(94,6),(94,8),(95,6),(95,8),(96,6),(96,8),(97,6),(97,8),(98,7),(99,7),(100,7),(101,7),(102,7),(103,7),(104,7),(105,8),(106,8),(107,8),(108,8),(109,9),(110,9),(111,9),(112,9),(113,9),(114,9),(115,9),(116,9),(117,9),(118,9),(119,10),(120,10),(121,10),(122,10),(123,10),(124,10),(125,10),(126,10),(127,10),(128,10),(129,10),(130,10),(131,10),(132,10),(133,10);
/*!40000 ALTER TABLE `cake_category` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cake_flavor`
--

DROP TABLE IF EXISTS `cake_flavor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cake_flavor` (
  `cake_id` int(11) NOT NULL,
  `flavor_id` int(11) NOT NULL,
  PRIMARY KEY (`cake_id`,`flavor_id`),
  KEY `fk_cf_flavor` (`flavor_id`),
  CONSTRAINT `fk_cf_cake` FOREIGN KEY (`cake_id`) REFERENCES `cake` (`cake_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cf_flavor` FOREIGN KEY (`flavor_id`) REFERENCES `flavor` (`flavor_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Junction table: many-to-many between cake and flavor';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cake_flavor`
--

LOCK TABLES `cake_flavor` WRITE;
/*!40000 ALTER TABLE `cake_flavor` DISABLE KEYS */;
/*!40000 ALTER TABLE `cake_flavor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `category`
--

DROP TABLE IF EXISTS `category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `category` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uq_category_name` (`category_name`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cake categories shown in the product catalog';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `category`
--

LOCK TABLES `category` WRITE;
/*!40000 ALTER TABLE `category` DISABLE KEYS */;
INSERT INTO `category` VALUES (1,'Anniversary','Celebration cakes for wedding anniversaries and milestone moments'),(2,'Birthday','Birthday cakes for all ages — from kids to milestone birthdays'),(3,'Christmas','Festive seasonal cakes for the Christmas holiday period'),(4,'Cupcake','Individual cupcakes and cupcake boxes for any occasion'),(5,'Engagement','Elegant cakes to celebrate an engagement or ring ceremony'),(6,'Graduation','Celebratory cakes for academic and professional graduations'),(7,'Number Cakes','Cakes shaped or decorated as numbers for milestone ages'),(8,'Party','Fun, vibrant cakes suitable for any party occasion'),(9,'Wedding','Multi-tier and elegant cakes designed for wedding receptions'),(10,'Others','Special occasion cakes that do not fit a standard category');
/*!40000 ALTER TABLE `category` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer`
--

DROP TABLE IF EXISTS `customer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer` (
  `customer_id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone_no` varchar(20) NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `role` enum('customer','admin') NOT NULL DEFAULT 'customer',
  `account_status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`customer_id`),
  UNIQUE KEY `uq_customer_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=137 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Customer contact information — guest-based, no password';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer`
--

LOCK TABLES `customer` WRITE;
/*!40000 ALTER TABLE `customer` DISABLE KEYS */;
INSERT INTO `customer` VALUES (18,'test1','test1@gmail.com','94 777174390','$2y$10$FVi.yJHfYLSzsrXhn3ZtE.5KldL.WjljqEA8DWR5C/3bCoTot2sN6','customer','active','2026-09-29 03:33:02'),(28,'Vinu Test','vinu@kukicakes.lk','+94771234567','$2y$10$mTJIFUjKcEmFWB/PHRWv4uZplq4Mkj9.FN5g/gH4ocu1GpmOD5b9q','customer','active','2026-09-29 05:55:05'),(36,'Cart User A','cart_user_a_1790917471@kukicakes.local','+94771112222','$2y$10$Fee6FMnjugLuSieAN1ZKQuWn4MZTjVGXvfLkS48VB2nbfVKq8xigC','customer','active','2026-10-02 05:04:31'),(37,'Cart User B','cart_user_b_1790917471@kukicakes.local','+94773334444','$2y$10$rGnMAMUe1XRsBrGPAHzqwOHgGW4Aa1WXIN6LDUPlCAEybcMkE3Aiu','customer','active','2026-10-02 05:04:31'),(40,'Cart User A','cart_user_a_1790917695@kukicakes.local','+94771112222','$2y$10$5xZi4z1RiFag3RUNLZTdGevIpAmk.2uuOzQYdtrEX4EROiNM.1/42','customer','active','2026-10-02 05:08:15'),(41,'Cart User B','cart_user_b_1790917695@kukicakes.local','+94773334444','$2y$10$jfGqx.NyrzHyZ6CT6W5j3OFaThHqkTJce8veFLiaCqsl60aNO/27a','customer','active','2026-10-02 05:08:16'),(43,'Cart User A','cart_user_a_1791078188@kukicakes.local','+94771112222','$2y$10$T0rMzP4kD2n1YdfihdCwZeWm7LnzJrNYeCNDIqrXU7KFa07fVURMS','customer','active','2026-10-04 01:43:08'),(44,'Cart User B','cart_user_b_1791078188@kukicakes.local','+94773334444','$2y$10$ZjaVY618D6UoMK4hFWp3deu4lPxxVX4CkRrVxJ/QVY64AqJuzb3Qe','customer','active','2026-10-04 01:43:08'),(49,'Cart User A','cart_user_a_1791080026@kukicakes.local','+94771112222','$2y$10$kTL951MocyX3Ihv0LL8qIeG9JRBSxzWRMeRRhLl5AjVE8Uu8W3VFG','customer','active','2026-10-04 02:13:46'),(50,'Cart User B','cart_user_b_1791080026@kukicakes.local','+94773334444','$2y$10$NgjJGhtTu5ZMvPKRI8mpTuDQTvARuDiAdoxvfjRim.tuPPfqS11yy','customer','active','2026-10-04 02:13:46'),(55,'Cart User A','cart_user_a_1791120403@kukicakes.local','+94771112222','$2y$10$jNBL3knQmNL4L3XLcvkPMuedbZJoqjFvh5hV0MPJnd.N0ZzDEevyC','customer','active','2026-10-04 13:26:43'),(56,'Cart User B','cart_user_b_1791120403@kukicakes.local','+94773334444','$2y$10$7HyPLGHMccqhcXuKDvdEoeWNX9SmQcYf4c/lwJ/I3fxcjLp4gmSzW','customer','active','2026-10-04 13:26:43'),(66,'Test Customer','test_cust_task3_1791758004@kukicakes.lk','+94771234567','$2y$10$8tXHd8d2OMF8McNoSBUqU.Bu9G4XZoWraSi.mtxk5u3rBFZUlaANO','customer','active','2026-10-11 22:33:25'),(67,'Test Customer','test_cust_task3_1791758208@kukicakes.lk','+94771234567','$2y$10$tf7Ro/ANT7e5uHtJeZ7hn.f/U0.gKMY0M4gSPJIqs.G0iVBuUj8SK','customer','active','2026-10-11 22:36:48'),(96,'amaya perera','amaya@email.com','0777174390','$2y$10$otQNstFTJ/WJLdr9126muuYHYcFMMSpOXHqiVXiPnmt74Mi0jR2He','customer','active','2026-10-12 23:52:05'),(101,'Cart User A','cart_user_a_1792282278@kukicakes.local','+94771112222','$2y$10$KmTIA9dn1lIr4/eciX9R6uCqZvFxWHX8gI0PZBL/whZJTZlKATelW','customer','active','2026-10-18 00:11:19'),(102,'Cart User B','cart_user_b_1792282278@kukicakes.local','+94773334444','$2y$10$KoSMjadlhiXmHF2u0a.NDeU92xiVlXPcF.Qe76wKWuMfZMTFRXclq','customer','active','2026-10-18 00:11:19'),(109,'Cart User A','cart_user_a_1792391397@kukicakes.local','+94771112222','$2y$10$UfAqCJTArViCRwPu0XzW8uj1a5B/bVqr0yFzuJS3yFIwvtRMRqn4q','customer','active','2026-10-19 06:29:57'),(110,'Cart User B','cart_user_b_1792391397@kukicakes.local','+94773334444','$2y$10$0kfPYSO8Ze9zfuJ68rWCaOpbmGmHUnvFtySHXJ44pwS9gFtVBeQdu','customer','active','2026-10-19 06:29:57'),(112,'Vinuthi Abeywardana','vinuthithedara@gmail.com','0777174391','$2y$10$dq3xYK0WQnY8lKpke0kj8eH7v8aw9J9XBL/3J5F4EuI1nJSkUTEO2','customer','active','2026-10-25 14:49:15'),(114,'Cart User A','cart_user_a_1792965293@kukicakes.local','+94771112222','$2y$10$Zx5bqqDWgJWWj1ywlTnUfupfH83J0yq0Oue7T5JQSm7Zy03Vg9EPO','customer','active','2026-10-25 21:54:53'),(115,'Cart User B','cart_user_b_1792965293@kukicakes.local','+94773334444','$2y$10$b4bXHN2UzfUs2cz3l9tLmuAoR1R8n.Fye/1PHNkXIK9HqHBUNVdOO','customer','active','2026-10-25 21:54:54'),(124,'Cart User A','cart_user_a_1793010051@kukicakes.local','+94771112222','$2y$10$pp1J.YuMEWq0jYeTYiL5UOPI8WUMTqDRMLMXckmUXns7ZhYu3fwD2','customer','active','2026-10-26 10:20:51'),(125,'Cart User B','cart_user_b_1793010051@kukicakes.local','+94773334444','$2y$10$ad4zE0sbcOvTpHXnmKhU2OZqxfeIJtELV5Xk0MQf2HZbEO9BRY/U2','customer','active','2026-10-26 10:20:52');
/*!40000 ALTER TABLE `customer` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `flavor`
--

DROP TABLE IF EXISTS `flavor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `flavor` (
  `flavor_id` int(11) NOT NULL AUTO_INCREMENT,
  `flavor_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`flavor_id`),
  UNIQUE KEY `uq_flavor_name` (`flavor_name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Available cake flavor options';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `flavor`
--

LOCK TABLES `flavor` WRITE;
/*!40000 ALTER TABLE `flavor` DISABLE KEYS */;
INSERT INTO `flavor` VALUES (1,'Not Sure Yet','Customer has not yet decided on a flavor — admin will follow up'),(2,'Vanilla Bean','Classic vanilla sponge made with real vanilla bean'),(3,'Chocolate','Rich, moist chocolate sponge'),(4,'Red Velvet','Smooth red velvet with cream cheese undertones'),(5,'Coffee','Coffee-infused sponge with mocha notes'),(6,'Butter Cake','Traditional Sri Lankan butter cake — dense and rich');
/*!40000 ALTER TABLE `flavor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inquiry`
--

DROP TABLE IF EXISTS `inquiry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inquiry` (
  `inquiry_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) DEFAULT NULL,
  `contact_name` varchar(100) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `contact_email` varchar(150) DEFAULT NULL,
  `status_id` int(11) NOT NULL DEFAULT 1,
  `flavor_id` int(11) DEFAULT NULL COMMENT 'NULL = Not Sure Yet',
  `cake_type` varchar(100) NOT NULL,
  `event_date` date NOT NULL,
  `no_of_servings` int(11) DEFAULT NULL,
  `budget_range` varchar(50) DEFAULT NULL,
  `design_description` text DEFAULT NULL,
  `inspiration_image` varchar(255) DEFAULT NULL COMMENT 'Uploaded file path',
  `inquiry_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`inquiry_id`),
  KEY `fk_inq_customer` (`customer_id`),
  KEY `fk_inq_status` (`status_id`),
  KEY `fk_inq_flavor` (`flavor_id`),
  CONSTRAINT `fk_inq_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_inq_flavor` FOREIGN KEY (`flavor_id`) REFERENCES `flavor` (`flavor_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_inq_status` FOREIGN KEY (`status_id`) REFERENCES `inquiry_status` (`status_id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_inq_servings` CHECK (`no_of_servings` is null or `no_of_servings` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Custom cake inquiries submitted by customers';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inquiry`
--

LOCK TABLES `inquiry` WRITE;
/*!40000 ALTER TABLE `inquiry` DISABLE KEYS */;
INSERT INTO `inquiry` VALUES (13,NULL,'test','94 777174390','test@gmail.com',1,4,'Graduation Cake','2026-10-29',43,'Rs. 25,000–50,000','cake for me',NULL,'2026-10-19 03:09:03'),(14,NULL,'test1','0777174332','test1@gmail.com',1,4,'Christmas Cake','2026-10-30',23,'Below Rs. 10,000','cake this','uploads/inquiries/inq_6f0e8be7a9ec91357704179f3455ced2.jpg','2026-10-19 03:19:52'),(15,NULL,'test3','94 777174390','test3@gmail.com',2,2,'Birthday Cake','2026-11-27',23,'Rs. 10,000–25,000','wedsfv','uploads/inquiries/inq_79b5dda01cc62f7d48dc90135d519dec.jpg','2026-10-19 03:21:37');
/*!40000 ALTER TABLE `inquiry` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inquiry_status`
--

DROP TABLE IF EXISTS `inquiry_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inquiry_status` (
  `status_id` int(11) NOT NULL AUTO_INCREMENT,
  `status_name` varchar(50) NOT NULL,
  PRIMARY KEY (`status_id`),
  UNIQUE KEY `uq_inquiry_status_name` (`status_name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lookup table for custom inquiry status values';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inquiry_status`
--

LOCK TABLES `inquiry_status` WRITE;
/*!40000 ALTER TABLE `inquiry_status` DISABLE KEYS */;
INSERT INTO `inquiry_status` VALUES (6,'Cancelled'),(5,'Completed'),(4,'Confirmed'),(2,'In Review'),(1,'Pending'),(3,'Quoted');
/*!40000 ALTER TABLE `inquiry_status` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_item`
--

DROP TABLE IF EXISTS `order_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_item` (
  `order_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `cake_id` int(11) DEFAULT NULL COMMENT 'Nullable — snapshot preserved if cake deleted',
  `flavor_id` int(11) DEFAULT NULL COMMENT 'FK to flavor — NULL if no flavor selected',
  `cake_name` varchar(150) NOT NULL COMMENT 'Snapshot of cake name at order time',
  `unit_price` decimal(10,2) NOT NULL COMMENT 'Snapshot of price at order time',
  `quantity` int(11) NOT NULL DEFAULT 1,
  `weight` varchar(100) DEFAULT NULL,
  `special_note` text DEFAULT NULL,
  PRIMARY KEY (`order_item_id`),
  KEY `fk_oi_order` (`order_id`),
  KEY `fk_oi_cake` (`cake_id`),
  KEY `fk_oi_flavor` (`flavor_id`),
  CONSTRAINT `fk_oi_cake` FOREIGN KEY (`cake_id`) REFERENCES `cake` (`cake_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_oi_flavor` FOREIGN KEY (`flavor_id`) REFERENCES `flavor` (`flavor_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_oi_price` CHECK (`unit_price` >= 0),
  CONSTRAINT `chk_oi_qty` CHECK (`quantity` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=381 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Line items per order — cake_name and unit_price are order-time snapshots';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_item`
--

LOCK TABLES `order_item` WRITE;
/*!40000 ALTER TABLE `order_item` DISABLE KEYS */;
INSERT INTO `order_item` VALUES (45,21,2,1,'Burgundy Ombre Heart Anniversary Cake',9000.00,1,'500g',NULL),(46,22,2,1,'Burgundy Ombre Heart Anniversary Cake',9000.00,1,'500g',NULL),(47,23,4,1,'Together Forever Gold Cascade Cake',10500.00,1,'500g',NULL),(48,24,4,1,'Together Forever Gold Cascade Cake',10500.00,1,'500g',NULL),(49,25,5,1,'Golden Drip Anniversary Cake',8000.00,1,'500g',NULL),(50,26,6,1,'Modern MuseRed Anniversary Cake',8000.00,1,'500g',NULL),(201,138,2,1,'Burgundy Ombre Heart Anniversary Cake',9000.00,1,'500g',NULL),(242,155,11,2,'Pastel Smiley Rainbow Kids Birthday Cake',5000.00,1,'500g',NULL),(243,156,11,2,'Pastel Smiley Rainbow Kids Birthday Cake',5000.00,1,'500g',NULL),(260,164,2,1,'Burgundy Ombre Heart Anniversary Cake',9000.00,1,'500g',NULL),(261,164,10,1,'Lucas\'s Dino Volcano Kids Adventure Birthday Cake',8800.00,2,'500g',NULL),(348,208,91,1,'Pearl Rose Ring Engagement Cake',18100.00,1,'500g',NULL),(349,208,121,NULL,'Golden Crosshatch Butter Cake',3100.00,1,'About 0.8 kg',NULL),(350,208,75,1,'Blush Rose Swirl Cupcakes',3300.00,1,'Box of 6 cupcakes',NULL),(351,209,121,NULL,'Golden Crosshatch Butter Cake',3100.00,1,'About 0.8 kg',NULL),(352,210,16,1,'Gold Drip 21st Birthday Cake',8700.00,1,'1.0 kg, matte fondant',NULL);
/*!40000 ALTER TABLE `order_item` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) DEFAULT NULL,
  `order_number` varchar(20) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone_no` varchar(20) NOT NULL,
  `delivery_address` text NOT NULL,
  `delivery_date` date NOT NULL,
  `delivery_time_slot` varchar(50) NOT NULL COMMENT 'e.g. Morning (8AM - 12PM)',
  `special_instructions` text DEFAULT NULL,
  `payment_method` enum('card','transfer','cash') NOT NULL,
  `payment_status` enum('pending','paid','failed','cancelled','charged_back') NOT NULL DEFAULT 'pending',
  `payhere_payment_id` varchar(50) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `delivery_fee` decimal(10,2) NOT NULL DEFAULT 500.00 COMMENT 'Rs. 500 flat delivery fee',
  `total` decimal(10,2) NOT NULL,
  `order_status` enum('pending','confirmed','in_progress','ready','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`order_id`),
  UNIQUE KEY `uq_order_number` (`order_number`),
  KEY `fk_ord_customer` (`customer_id`),
  CONSTRAINT `fk_ord_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_ord_subtotal` CHECK (`subtotal` >= 0),
  CONSTRAINT `chk_ord_del_fee` CHECK (`delivery_fee` >= 0),
  CONSTRAINT `chk_ord_total` CHECK (`total` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=225 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Confirmed checkout orders placed through the e-commerce flow';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (21,18,'KC-20261005-34S7UM','Vinuthi Abeywardana','vinuthithedara@gmail.com','94 777174390','18 Samudra Mawatha','2026-10-22','Morning (8AM - 12PM)','zexrctfvgybhn','card','pending',NULL,9000.00,500.00,9500.00,'pending','2026-10-05 15:25:28'),(22,18,'KC-20261005-83XXXZ','Vinuthi Abeywardana','vinuthithedara@gmail.com','94 777174390','18 Samudra Mawatha','2026-10-07','Afternoon (12PM - 4PM)','esdv','cash','pending',NULL,9000.00,500.00,9500.00,'pending','2026-10-05 15:41:54'),(23,18,'KC-20261005-7V3MKN','Vinuthi Abeywardana','vinuthithedara@gmail.com','94 777174390','18 Samudra Mawatha','2026-10-07','Afternoon (12PM - 4PM)','esdv','card','pending',NULL,10500.00,500.00,11000.00,'pending','2026-10-05 15:42:32'),(24,18,'KC-20261005-44LWWK','Vinuthi Abeywardana','vinuthithedara@gmail.com','94 777174390','18 Samudra Mawatha','2026-10-07','Afternoon (12PM - 4PM)','eedf','card','pending',NULL,10500.00,500.00,11000.00,'pending','2026-10-05 15:49:20'),(25,18,'KC-20261005-6JSRKN','Vinuthi Abeywardana','vinuthithedara@gmail.com','94 777174390','18 Samudra Mawatha','2026-10-07','Afternoon (12PM - 4PM)','eedf','card','cancelled',NULL,8000.00,500.00,8500.00,'pending','2026-10-05 16:01:33'),(26,18,'KC-20261005-C9E2MM','Vinuthi Abeywardana','vinuthithedara@gmail.com','94 777174390','18 Samudra Mawatha','2026-10-07','Afternoon (12PM - 4PM)','eedf','card','cancelled',NULL,8000.00,500.00,8500.00,'pending','2026-10-05 16:03:18'),(138,96,'KC-20261013-9B2LGG','amaya abeysinghe','amaya@gmail.com','94 777174390','18 Samudra Mawatha','2026-10-22','Afternoon (12PM - 4PM)','dwefd','card','pending',NULL,9000.00,500.00,9500.00,'pending','2026-10-12 23:53:26'),(155,96,'KC-20261017-UZDKQM','amaya abeysinghe','amaya@gmail.com','0777174390','18 Samudra Mawatha','2026-10-19','Morning (8AM - 12PM)','wefgrtgh','card','pending',NULL,5000.00,500.00,5500.00,'pending','2026-10-16 23:50:28'),(156,96,'KC-20261017-MZY349','amaya abeysinghe','amaya@gmail.com','94 777174390','18 Samudra Mawatha','2026-10-20','Afternoon (12PM - 4PM)','defgd','card','paid','320032673612',5000.00,500.00,5500.00,'confirmed','2026-10-16 23:52:05'),(164,96,'KC-20261018-9HMYH2','Vinuthi Abeywardana','vinuthithedara@gmail.com','94 777174390','18 Samudra Mawatha','2026-10-29','Morning (8AM - 12PM)','hffff','card','pending',NULL,26600.00,500.00,27100.00,'pending','2026-10-18 01:09:48'),(208,112,'KC-20261026-2J7SJL','Vinuthi Abeywardana','vinuthithedara@gmail.com','94 777174391','18 Samudra Mawatha','2026-10-29','Morning (8AM - 12PM)',NULL,'transfer','pending',NULL,24500.00,500.00,25000.00,'pending','2026-10-26 12:17:35'),(209,112,'KC-20261026-BXD24T','Vinuthi Abeywardana','vinuthithedara@gmail.com','0777174390','18 Samudra Mawatha','2026-10-29','Morning (8AM - 12PM)',NULL,'cash','pending',NULL,3100.00,500.00,3600.00,'pending','2026-10-26 12:18:19'),(210,112,'KC-20261026-FCNMH6','Vinuthi Abeywardana','vinuthithedara@gmail.com','0777174390','18 Samudra Mawatha','2026-10-28','Morning (8AM - 12PM)',NULL,'card','pending',NULL,8700.00,500.00,9200.00,'pending','2026-10-26 12:18:59');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `reset_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`reset_id`),
  KEY `idx_token_hash` (`token_hash`),
  KEY `idx_customer_id` (`customer_id`),
  CONSTRAINT `fk_reset_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
INSERT INTO `password_resets` VALUES (7,18,'6795cdb72b5242e8a7efeb8905bc331a7edd96b3da7e09bb5eac737e64113fcd','2026-09-29 11:17:59',NULL,'2026-09-29 04:47:59'),(16,28,'8cfff22d170bb327a2fbea5e10a2adc8558d8d63e8b0151cf06b786db2e3df97','2026-10-02 11:15:09','2026-10-02 10:15:10','2026-10-02 04:45:09'),(21,28,'079da1ed5ea6de93063aa9bf204d436f4569c947222be166338300c9a4a6df9c','2026-10-02 11:32:24','2026-10-02 10:32:24','2026-10-02 05:02:24'),(24,28,'0f176569e88fee0f25bb409ce7df92dd9c13309bbbe3f848bb527191ba9ca495','2026-10-02 11:38:06','2026-10-02 10:38:07','2026-10-02 05:08:06'),(37,28,'29cd7a05ec3c29db1dc2a753288682bdcf8b21659dd9f1e930cfaafff39ebb16','2026-10-26 04:36:18','2026-10-26 03:36:19','2026-10-25 22:06:18'),(40,28,'20a76de333cd8feecad6110b35537b81199d02194bc49556bbb263c514aedf09','2026-10-26 17:01:38','2026-10-26 16:01:38','2026-10-26 10:31:38'),(41,28,'1584145a5eab25cbffb4eeeb82bb9fe1d9b7748c0c8292ec2f5f10c9ed7f6195','2026-10-26 19:04:23','2026-10-26 18:04:24','2026-10-26 12:34:23');
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_addresses`
--

DROP TABLE IF EXISTS `user_addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_addresses` (
  `address_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `addr_type` enum('shipping','billing') NOT NULL DEFAULT 'shipping',
  `full_name` varchar(100) NOT NULL,
  `line1` varchar(200) NOT NULL,
  `line2` varchar(200) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `postal` varchar(20) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`address_id`),
  KEY `fk_addr_customer` (`customer_id`),
  CONSTRAINT `fk_addr_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_addresses`
--

LOCK TABLES `user_addresses` WRITE;
/*!40000 ALTER TABLE `user_addresses` DISABLE KEYS */;
INSERT INTO `user_addresses` VALUES (2,112,'shipping','Vinuthi Abeywardana','18 Samudra Mawatha','','Panadura','','',1,'2026-10-26 12:15:40'),(3,112,'billing','Vinuthi Abeywardana','18 Samudra Mawatha','','Panadura','','',1,'2026-10-26 12:15:53');
/*!40000 ALTER TABLE `user_addresses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_cart_items`
--

DROP TABLE IF EXISTS `user_cart_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_cart_items` (
  `cart_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `weight` varchar(100) NOT NULL DEFAULT 'Standard',
  `flavor` varchar(100) NOT NULL DEFAULT '',
  `note` text DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `item_data` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cart_item_id`),
  KEY `idx_cart_customer` (`customer_id`),
  CONSTRAINT `fk_cart_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=253 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_cart_items`
--

LOCK TABLES `user_cart_items` WRITE;
/*!40000 ALTER TABLE `user_cart_items` DISABLE KEYS */;
INSERT INTO `user_cart_items` VALUES (6,37,7,'750g','Caramel','For Party',5,'{\"id\":7,\"name\":\"Golden Caramel Drizzle\",\"price\":4800,\"weight\":\"750g\",\"flavor\":\"Caramel\",\"note\":\"For Party\",\"qty\":5,\"img\":\"\"}','2026-10-02 05:04:31','2026-10-02 05:04:31'),(13,41,7,'750g','Caramel','For Party',5,'{\"id\":7,\"name\":\"Golden Caramel Drizzle\",\"price\":4800,\"weight\":\"750g\",\"flavor\":\"Caramel\",\"note\":\"For Party\",\"qty\":5,\"img\":\"\"}','2026-10-02 05:08:16','2026-10-02 05:08:16'),(23,44,7,'750g','Caramel','For Party',5,'{\"id\":7,\"name\":\"Golden Caramel Drizzle\",\"price\":4800,\"weight\":\"750g\",\"flavor\":\"Caramel\",\"note\":\"For Party\",\"qty\":5,\"img\":\"\"}','2026-10-04 01:43:08','2026-10-04 01:43:08'),(35,50,7,'750g','Caramel','For Party',5,'{\"id\":7,\"name\":\"Golden Caramel Drizzle\",\"price\":4800,\"weight\":\"750g\",\"flavor\":\"Caramel\",\"note\":\"For Party\",\"qty\":5,\"img\":\"\"}','2026-10-04 02:13:47','2026-10-04 02:13:47'),(47,56,7,'750g','Caramel','For Party',5,'{\"id\":7,\"name\":\"Golden Caramel Drizzle\",\"price\":4800,\"weight\":\"750g\",\"flavor\":\"Caramel\",\"note\":\"For Party\",\"qty\":5,\"img\":\"\"}','2026-10-04 13:26:43','2026-10-04 13:26:43'),(132,102,7,'750g','Caramel','For Party',5,'{\"id\":7,\"name\":\"Golden Caramel Drizzle\",\"price\":4800,\"weight\":\"750g\",\"flavor\":\"Caramel\",\"note\":\"For Party\",\"qty\":5,\"img\":\"\"}','2026-10-18 00:11:19','2026-10-18 00:11:19'),(143,96,2,'500g','Not sure yet','',1,'{\"id\":2,\"name\":\"Burgundy Ombre Heart Anniversary Cake\",\"cat\":\"anniversary\",\"price\":9000,\"badge\":\"anniversary\",\"img\":\"docs/cakes/Anniversary/Anniversary cake2.jpeg\",\"desc\":\"A two-tone burgundy-to-ivory ombré buttercream cake finished with a jagged gold-leaf border and gold sugar pearls, accented with double gold wire hearts and a black \\\"Happy Anniversary\\\" topper.\",\"availability\":1,\"stock_quantity\":null,\"categories\":[{\"id\":1,\"name\":\"Anniversary\",\"slug\":\"anniversary\"}],\"qty\":1,\"weight\":\"500g\",\"flavor\":\"Not sure yet\",\"note\":\"\"}','2026-10-18 01:20:54','2026-10-18 01:20:54'),(144,96,10,'500g','Not sure yet','',2,'{\"id\":10,\"name\":\"Lucas\'s Dino Volcano Kids Adventure Birthday Cake\",\"cat\":\"birthday party\",\"price\":8800,\"badge\":\"Kids\",\"img\":\"docs/cakes/Birthday/Kids Birthday Cake4.jpeg\",\"desc\":\"A light-blue dinosaur-themed cake with a fondant volcano, palm leaves and colorful dinosaur figures, personalized with a \\\"LUCAS 3\\\" wood-effect plaque.\",\"availability\":1,\"stock_quantity\":null,\"categories\":[{\"id\":2,\"name\":\"Birthday\",\"slug\":\"birthday\"},{\"id\":8,\"name\":\"Party\",\"slug\":\"party\"}],\"qty\":2,\"weight\":\"500g\",\"flavor\":\"Not sure yet\",\"note\":\"\"}','2026-10-18 01:20:54','2026-10-18 01:20:54'),(150,110,7,'750g','Caramel','For Party',5,'{\"id\":7,\"name\":\"Golden Caramel Drizzle\",\"price\":4800,\"weight\":\"750g\",\"flavor\":\"Caramel\",\"note\":\"For Party\",\"qty\":5,\"img\":\"\"}','2026-10-19 06:29:58','2026-10-19 06:29:58'),(176,115,7,'750g','Caramel','For Party',5,'{\"id\":7,\"name\":\"Golden Caramel Drizzle\",\"price\":4800,\"weight\":\"750g\",\"flavor\":\"Caramel\",\"note\":\"For Party\",\"qty\":5,\"img\":\"\"}','2026-10-25 21:54:54','2026-10-25 21:54:54'),(208,125,7,'750g','Caramel','For Party',5,'{\"id\":7,\"name\":\"Golden Caramel Drizzle\",\"price\":4800,\"weight\":\"750g\",\"flavor\":\"Caramel\",\"note\":\"For Party\",\"qty\":5,\"img\":\"\"}','2026-10-26 10:20:52','2026-10-26 10:20:52'),(209,28,1,'500g','Not sure yet','',3,'{\"id\":1,\"name\":\"Golden Pearl Anniversary Cake\",\"cat\":\"anniversary\",\"price\":10500,\"badge\":\"Bestseller\",\"img\":\"docs/cakes/Anniversary/Anniversary cake1.jpeg\",\"desc\":\"A tall ivory buttercream cake scattered with cascading gold sugar pearls and flanked by deep red rose clusters, topped with a gold script \\\"Happy Anniversary\\\" topper.\",\"qty\":3,\"weight\":\"500g\",\"flavor\":\"Not sure yet\",\"note\":\"\"}','2026-10-26 10:28:18','2026-10-26 10:28:18'),(210,28,18,'1.0 kg, textured buttercream','Not sure yet','',1,'{\"id\":18,\"name\":\"Rustic Baby\'s Breath 21st Cake\",\"cat\":\"birthday\",\"price\":5800,\"sale_unit\":\"Whole cake\",\"confirmed_size\":\"1.0 kg, textured buttercream\",\"badge\":\"21st\",\"img\":\"docs/cakes/Birthday/21st birthday cake5.jpeg\",\"desc\":\"An ivory textured buttercream cake decorated with dried baby\'s breath sprigs and a written \\\"21\\\" numeral, presented with a \\\"Happy 21st Birthday\\\" gift tag.\",\"availability\":1,\"stock_quantity\":null,\"categories\":[{\"id\":2,\"name\":\"Birthday\",\"slug\":\"birthday\"}],\"qty\":1,\"weight\":\"1.0 kg, textured buttercream\",\"flavor\":\"Not sure yet\",\"note\":\"\"}','2026-10-26 10:28:18','2026-10-26 10:28:18'),(211,28,111,'4-tier, about 8 kg','Not sure yet','',1,'{\"id\":111,\"name\":\"J&N Pearl & Rose Wedding Cake\",\"cat\":\"wedding\",\"price\":66500,\"sale_unit\":\"Whole cake\",\"confirmed_size\":\"4-tier, about 8 kg\",\"badge\":\"wedding\",\"img\":\"docs/cakes/Wedding/WeddingCake3.jpeg\",\"desc\":\"A classic three-tier white fondant cake finished with delicate pearl-dot piping, clusters of fresh white roses and baby\'s breath, and a gold script \\\"J&N\\\" monogram topper.\",\"availability\":1,\"stock_quantity\":null,\"categories\":[{\"id\":9,\"name\":\"Wedding\",\"slug\":\"wedding\"}],\"qty\":1,\"weight\":\"4-tier, about 8 kg\",\"flavor\":\"Not sure yet\",\"note\":\"\"}','2026-10-26 10:28:18','2026-10-26 10:28:18'),(240,112,8,'1.0 kg, buttercream + fondant figure','Not sure yet','',1,'{\"id\":8,\"name\":\"Hello Kitty Kids Birthday Cake\",\"cat\":\"birthday party\",\"price\":8100,\"sale_unit\":\"Whole cake\",\"confirmed_size\":\"1.0 kg, buttercream + fondant figure\",\"badge\":\"Kids\",\"img\":\"docs/cakes/Birthday/Kids Birthday Cake2.jpeg\",\"desc\":\"A white and pink Hello Kitty themed cake with piped bows and polka dots, a Hello Kitty figure, a pink \\\"5\\\" numeral, and a \\\"Happy Birthday\\\" heart plaque.\",\"availability\":1,\"stock_quantity\":null,\"categories\":[{\"id\":2,\"name\":\"Birthday\",\"slug\":\"birthday\"},{\"id\":8,\"name\":\"Party\",\"slug\":\"party\"}],\"qty\":1,\"weight\":\"1.0 kg, buttercream + fondant figure\",\"flavor\":\"Not sure yet\",\"note\":\"\"}','2026-10-26 12:20:28','2026-10-26 12:20:28');
/*!40000 ALTER TABLE `user_cart_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'kukicakes_db'
--

--
-- Dumping routines for database 'kukicakes_db'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-26 19:47:32
