<?php
// الأسماء المستخدمة في الملفات المختلفة:

// 1. في ملف Stats/User.php:
// xf_harment_xbttracker_user_stats
// xf_harment_xbttracker_torrent

// 2. في ملفات المتحكمات المختلفة:
// xf_xbt_user_stats
// xf_xbt_torrents
// xf_xbt_warnings
// xf_xbt_categories
// xf_xbt_user_bonus_history
// xf_xbt_log

// يجب التحقق من أسماء الجداول الفعلية في قاعدة البيانات وتوحيدها.
// قد تكون هناك حاجة لتعديل إما أسماء الجداول في قاعدة البيانات أو تعديل الاستعلامات في الشيفرة.

/**
 * هذا البرنامج النصي يقوم بفحص أسماء الجداول المستخدمة في القاعدة 
 * ومقارنتها بالأسماء المستخدمة في الشيفرة
 * 
 * طريقة الاستخدام: قم بتعديل اسم المستخدم وكلمة المرور واسم قاعدة البيانات
 * ثم قم بتشغيل البرنامج النصي:
 * 
 * php check_tables.php
 */

// معلومات الاتصال بقاعدة البيانات
$dbHost = 'localhost';
$dbUser = 'phpmyadmin';
$dbPass = '222555@ASt';
$dbName = '2.3.6';

// قائمة أسماء الجداول المستخدمة في الشيفرة
$expectedTables = [
    // الأسماء المستخدمة في Stats/User.php
    'xf_harment_xbttracker_user_stats',
    'xf_harment_xbttracker_torrent',
    
    // الأسماء المستخدمة في متحكمات الإدارة
    'xf_xbt_user_stats',
    'xf_xbt_torrents',
    'xf_xbt_warnings',
    'xf_xbt_categories',
    'xf_xbt_user_bonus_history',
    'xf_xbt_log'
];

try {
    // الاتصال بقاعدة البيانات
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // الحصول على قائمة جميع الجداول في قاعدة البيانات
    $stmt = $pdo->query("SHOW TABLES");
    $actualTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // البحث عن الجداول المتوقعة في القائمة الفعلية
    echo "نتائج فحص الجداول:\n\n";
    
    foreach ($expectedTables as $tableName) {
        if (in_array($tableName, $actualTables)) {
            echo "✓ الجدول '{$tableName}' موجود\n";
        } else {
            echo "✗ الجدول '{$tableName}' غير موجود\n";
        }
    }
    
    // البحث عن جداول مشابهة قد تكون هي المطلوبة
    echo "\nالجداول المشابهة المحتملة:\n";
    
    foreach ($actualTables as $tableName) {
        if (strpos($tableName, 'xbt') !== false || 
            strpos($tableName, 'harment') !== false || 
            strpos($tableName, 'tracker') !== false || 
            strpos($tableName, 'torrent') !== false) {
            
            if (!in_array($tableName, $expectedTables)) {
                echo "- {$tableName}\n";
            }
        }
    }
    
} catch (PDOException $e) {
    echo "خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage() . "\n";
}