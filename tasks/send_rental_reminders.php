<?php
/**
 * Run this script once per day with Windows Task Scheduler or a server cron job.
 * Example: C:\xampp\php\php.exe D:\projects\RentalNetwork\tasks\send_rental_reminders.php
 */
require_once __DIR__ . '/../includes/db.php';

$sentCount = send_rental_due_reminders($pdo);
echo 'Rental reminders processed. New notifications: ' . $sentCount . PHP_EOL;
