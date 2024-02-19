CREATE TABLE `guest_token` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `user_id` INT NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `token` VARCHAR(190) NOT NULL,
    `confirmed` TINYINT(1) NOT NULL,
    `created` DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
    INDEX IDX_4AC9362FA76ED395 (`user_id`),
    INDEX IDX_4AC9362F5F37A13B (`token`),
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

ALTER TABLE `guest_token` ADD CONSTRAINT FK_4AC9362FA76ED395 FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE;

# If module was uninstalled/reinstalled, reactivate the guests.
UPDATE user SET is_active = 1 WHERE role = "guest";
