# TMPAY API Document

- **Last updated:** 03/11/2021
- **หัวข้อ:** การเชื่อมต่อกับระบบ TMPAY โดยใช้ PHP

## สารบัญ

1. [Introduction](#introduction)
2. [Overview System](#overview-system)
3. [ส่วนการรับรหัสบัตรเงินสดจากลูกค้า](#1-ส่วนการรับรหัสบัตรเงินสดจากลูกค้า)
   - [ตัวแปรในการรับรหัสบัตรเงินสดจากลูกค้า](#ตัวแปรในการรับรหัสบัตรเงินสดจากลูกค้า)
   - [สถานะต่างๆ ที่ลูกค้าจะได้รับ (Status Response)](#สถานะต่างๆ-ที่ลูกค้าจะได้รับ-status-response)
   - [ตัวอย่างโค้ดภาษา PHP (ฝั่งรับรหัสบัตรเงินสด)](#ตัวอย่างโค้ดภาษา-php-ฝั่งรับรหัสบัตรเงินสด)
4. [ส่วนการรับผลการทำรายการจาก TMPAY](#2-ส่วนการรับผลการทำรายการจาก-tmpay)
   - [ตัวแปรที่ใช้ในการส่งรหัสบัตรเงินสดถึง TMPAY](#ตัวแปรที่ใช้ในการส่งรหัสบัตรเงินสดถึง-tmpay)
   - [ตัวอย่างโค้ดภาษา PHP (ฝั่งรับผลการทำรายการ)](#ตัวอย่างโค้ดภาษา-php-ฝั่งรับผลการทำรายการ)
5. [การทดสอบระบบ TMPAY](#3-การทดสอบระบบ-tmpay)

---

## Introduction

บริการ TMPAY เป็นบริการที่ให้ร้านค้าออนไลน์สามารถเปิดรับชำระเงินออนไลน์ผ่านบัตรเงินสดทรูมันนี่ ซึ่งร้านค้าสามารถได้รับผลการทำรายการภายใน 1-3 นาที หลังจากที่ลูกค้าทำรายการ (ไม่รวมในกรณีที่ระบบทรูมันนี่ขัดข้อง)

เมื่อลูกค้าที่ต้องการซื้อสินค้าทำการกดเติมเงิน ระบบร้านค้าจะทำการเชื่อมต่อกับระบบ TMPAY เพื่อส่ง **รหัสบัตรเงินสด 14 หลัก** , **รหัสร้านค้า (merchant_id)** และ **URL สำหรับรับผลการทำรายการ (resp_url)** ในรูปแบบ **GET Method**

หลังจากที่ระบบ TMPAY ทำรายการกับ บริษัท ทรูมันนี่ จำกัด สำเร็จ ระบบ TMPAY จะส่งผลการทำรายการไปยังระบบร้านค้า

## Overview System

รูปภาพแสดงการทำงานของระบบ TMPAY

```
                       ┌───────────────────┐
   (1) Payment Request │   Merchant UI     │  (2) Status Response
   merchant_id         │   (เว็บร้านค้า)     │  transaction_id / password
   password            │                   │  amount / status
   resp_url            └─────────┬─────────┘
   channel                        │ Request / Response
                                  ▼
                       ┌───────────────────┐
                       │     TMPAY API     │
                       └─────────┬─────────┘
                                 │ Request / Billing
                                 ▼
                       ┌───────────────────┐
                       │ True Money Co.,Ltd.│
                       └───────────────────┘
```

การเชื่อมต่อระบบการรับชำระเงินกับ TMPAY เว็บร้านค้าจะมีการติดต่อกับ TMPAY 2 ส่วน โดย API จะเป็นแบบ **RESTful API** ซึ่งจะมีการทำงานที่รับข้อมูลบัตรเงินสดมาตรวจสอบก่อน หลังจากนั้นจะทำการส่งผลการตรวจสอบข้อมูลกลับไปหาเซิร์ฟเวอร์ของลูกค้า ดังนี้

1. **ส่วนการรับรหัสบัตรเงินสดจากลูกค้า** เป็นส่วนที่เว็บไซต์ร้านค้ารับรหัสบัตรเงินสดจากทรูมันนี่ เพื่อจัดเก็บลงฐานข้อมูลของระบบร้านค้า และส่งรหัสบัตรเงินสดมาที่ระบบ TMPAY จากนั้นร้านค้าแจ้งลูกค้าว่า "รับรายการสำเร็จ" แล้ว redirect ลูกค้ากลับไปยังหน้าที่แสดงรายการบัตรเงินสดของลูกค้า
2. **ส่วนการรับผลการทำรายการจาก TMPAY** เป็นส่วนที่ร้านค้ารับผลการชำระเงินจากระบบ TMPAY

---

## 1. ส่วนการรับรหัสบัตรเงินสดจากลูกค้า

หลังจากที่ลูกค้ากรอกรหัสบัตรเงินสด 14 หลักผ่านเว็บไซต์ของร้านค้า เว็บไซต์ร้านค้าจะทำการตรวจสอบเบื้องต้นว่ารหัสบัตรเงินสดมีรูปแบบที่ถูกต้องหรือไม่ (ครบ 14 หลัก และเป็นตัวเลขเท่านั้น) (รวมถึงให้ลูกค้าเลือกช่องทางการชำระเงินแล้ว กรณีเลือกช่องทางการชำระเงินที่เว็บไซต์ร้านค้า) เว็บไซต์ร้านค้าจะมีการติดต่อกับระบบทรู TMPAY เพื่อให้ตรวจสอบบัตรเงินสด โดยมีขั้นตอนในการติดต่อ ดังนี้

1. เว็บร้านค้าทำการส่งคำสั่งร้องขอ `merchant_id` , `password` , `resp_url` ถึง TMPAY (**HTTPS with GET Method**)
2. ระบบ TMPAY รับรายการพร้อมแจ้งผลการรับรายการ (ไม่ใช่ผลการทำรายการกับ บ.ทรูมันนี่)
3. ระบบ TMPAY ติดต่อระบบเว็บร้านค้าผ่าน `resp_url` ที่ระบุไว้ในข้อ 1 เพื่อแจ้ง `transaction_id` , `password` , `amount` และ `status` ของบัตรเงินสด

### ตัวแปรในการรับรหัสบัตรเงินสดจากลูกค้า

**Script URL:** `https://www.tmpay.net/TPG/backend.php` (GET Method)

| Parameter | รายละเอียด | ตัวอย่าง |
|-----------|------------|---------|
| `merchant_id` | รหัสร้านค้า (A-Z, 0-9 ความยาว 10 หลัก) | `TMPAY` |
| `password` | รหัสบัตรเงินสด (0-9 ความยาว 14 หลัก) | `01234567890123` |
| `resp_url` | URL สำหรับการรับผลการตรวจสอบ | `http://www.mywebsite.com/tmpay_result.php` |
| `channel` | ประเภทบัตรเงินสด:<br>`truemoney` = บัตรเงินสดทรูมันนี่<br>`razer_gold_pin` = Razer Gold PIN | `truemoney` |

### สถานะต่างๆ ที่ลูกค้าจะได้รับ (Status Response)

- `SUCCEED|TRANSACTION_ID` — ได้รับข้อมูลเรียบร้อยแล้ว พร้อมแจ้ง Transaction ID (A-Z, 0-9 ความยาว 10 หลัก) เช่น `SUCCEED|XYZ1234567`
- `ERROR|INVALID_MERCHANT_ID` — รหัสร้านค้าไม่ถูกต้อง หรือไม่มีในระบบ
- `ERROR|INVALID_PASSWORD` — รูปแบบรหัสบัตรเงินสดไม่ถูกต้อง
- `ERROR|INVALID_RESP_URL` — รูปแบบ URL ไม่ถูกต้อง

### ตัวอย่างโค้ดภาษา PHP (ฝั่งรับรหัสบัตรเงินสด)

```php
<?php
/*
 * ฝั่งส่งรหัสบัตรเงินสดไปยัง TMPAY (Payment Request)
 * ตัวอย่างการบันทึกข้อมูลสถานะ "รอตรวจสอบ" (pending) ลงฐานข้อมูลร้านค้า
 * ที่นี่ยังเป็นการแจ้งว่า TMPAY รับรายการแล้ว ยังไม่ใช่ผลการทำรายการจริง
 * ผลการทำรายการจะถูกส่งกลับผ่าน callback (resp_url) ภายหลัง
 * ให้ตรวจสอบค่าที่ส่งมาในส่วนที่ 2 (ฝั่งรับผลการทำรายการ)
 */
function tmn_refill ($truemoney_password)
{
    /* TODO: บันทึกค่ารอ (pending) ลงฐานข้อมูลร้านค้า พร้อมเก็บ user_id ของผู้เติมเงิน
       เช่น INSERT INTO tmpay_transactions (user_id, password, status) VALUES (?, ?, 'pending'); */

    if(function_exists('curl_init'))
    {
        /* สร้าง URL ส่งค่า merchant_id, password, resp_url ไปยัง TMPAY (GET Method) */
        $curl = curl_init('https://www.tmpay.net/TPG/backend.php?merchant_id=TMPAY&password=' . $truemoney_password . '&resp_url=http://www.mywebsite.com/tmpay_result.php');
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_HEADER, FALSE);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        $curl_content = curl_exec($curl);   /* รับผลตอบกลับว่า TMPAY รับรายการแล้วหรือไม่ */
        curl_close($curl);
    }
    else
    {
        /* กรณีไม่มี cURL ให้ใช้ file_get_contents แทน */
        $curl_content = file_get_contents('https://www.tmpay.net/TPG/backend.php?merchant_id=TMPAY&password=' . $truemoney_password . '&resp_url=http://www.mywebsite.com/tmpay_result.php');
    }

    /* ตรวจสอบว่า TMPAY รับรายการแล้ว (SUCCEED => ส่งไปแล้ว รอผลจริงภายหลัง) */
    if(strpos($curl_content,'SUCCEED') !== FALSE)
    {
        /* ข้อความตอบกลับมาเป็น "SUCCEED|XYZ1234567"
           วิธีแปลง: แยกด้วยเครื่องหมาย | แล้วเอาข้อความด้านหลัง SUCCEED| มาใช้ */
        $parts = explode('|', $curl_content);                    /* => ['SUCCEED', 'XYZ1234567'] */
        $transaction_id = isset($parts[1]) ? trim($parts[1]) : ''; /* => 'XYZ1234567' */

        /* บันทึก TRANSACTION_ID ลงเรคคอร์ดที่ pending ไว้ (อ้างอิงด้วย password)
        $this->db->query("UPDATE tmpay_transactions SET transaction_id = ?, status = 'awaiting_result' WHERE password = ?", $transaction_id, $truemoney_password);
        */

        return true;
    }
    else
    {
        return false;
    }
}
?>
```

> **หมายเหตุ:** ในส่วนนี้บัตรเงินสดทรูมันนี่จะยังอยู่ในระหว่างการตรวจสอบโดย TMPAY.NET ลูกค้าและร้านค้าจะยังไม่ทราบผลการทำรายการและมูลค่าบัตรเงินสดทรูมันนี่ เนื่องจาก TMPAY.NET จะต้องรอผลการทำรายการจากทรูมันนี่ ซึ่งอาจจะใช้เวลาถึง 1-5 นาที โดยร้านค้าจะต้องสร้างสคริปต์เพื่อรับผลการทำรายการตามวิธีการในส่วนที่ 2

---

## 2. ส่วนการรับผลการทำรายการจาก TMPAY

หลังจากที่ TMPAY ได้ตรวจสอบข้อมูลบัตรเรียบร้อยแล้ว ก็จะส่งข้อมูลกลับมาที่ URL `resp_url` ตามที่ระบุไว้ใน Parameter ในขั้นตอนที่ (1) ในรูปแบบ **GET Method** ดังนี้

### ตัวแปรที่ใช้ในการส่งรหัสบัตรเงินสดถึง TMPAY

**Script URL:** ตามที่ระบุไว้ใน `resp_url` (GET Method)

| Parameter | Type | รายละเอียด | ตัวอย่าง |
|-----------|------|------------|---------|
| `transaction_id` | `varchar(10)` | Transaction ID ของผลการตรวจสอบ (A-Z, 0-9 ความยาว 10 หลัก) | `XYZ1234567` |
| `password` | `varchar(14)` | รหัสบัตรเงินสดทรูมันนี่ (0-9 ความยาว 14 หลัก) | `01234567890123` |
| `real_amount` | `double(10,2)` | จำนวนเงินที่ได้รับ | `20.00`, `50.00`, `90.00`, `150.00`, `300.00`, `500.00`, `1000.00` |
| `status` | `integer` | ผลการตรวจสอบ (1, 3, 4, 5) | `1` |

**สถานะ (`status`)**

| ค่า | ความหมาย |
|-----|----------|
| `1` | การเติมเงินสำเร็จ |
| `3` | บัตรเงินสดถูกใช้ไปแล้ว |
| `4` | รหัสบัตรเงินสดไม่ถูกต้อง |
| `5` | เป็นบัตรทรูมูฟ (ไม่ใช่บัตรทรูมันนี่) |

> **หมายเหตุ:** Script URL จะต้องมีการตอบสถานะกลับมาที่ระบบ โดยมีคำว่า `SUCCEED` หรือ `ERROR` ประกอบอยู่ เช่น `SUCCEED|UID=10` , `ERROR|CARD_DOESNT_EXIST` เพื่อให้ระบบบันทึกการส่งข้อมูล และมั่นใจว่าปลายทางได้รับเรียบร้อยแล้ว

### ตัวอย่างโค้ดภาษา PHP (ฝั่งรับผลการทำรายการ)

```php
<?php
/*
 * ตัวอย่างสคริปต์รับผลการทำรายการ (Callback) จาก TMPAY
 * TMPAY จะเรียก URL resp_url ด้วย GET Method พร้อมส่งค่า:
 *   transaction_id / password / real_amount / status
 */
$transaction_id = $_GET['transaction_id'];   /* ส่งย้อนกลับมาเพื่อยืนยันว่าเป็นรายการเดียวกันกับที่บันทึกไว้ */
$password       = $_GET['password'];
$real_amount    = $_GET['real_amount'];
$status         = $_GET['status'];           /* 1 = สำเร็จ, 3 = บัตรถูกใช้แล้ว, 4 = รหัสบัตรไม่ถูกต้อง, 5 = เป็นบัตรทรูมูฟ */

if( $status == 1 )
{
    /* Code เพิ่มเครดิตและอัปเดตสถานะเติมเงินที่นี่ */
    /* เช่น (ทั้ง 2 ตาราง ใช้ password + transaction_id ตรงกับที่บันทึกไว้ตอนฝั่งรับรหัสบัตรเงินสด)
    $tmpay = $this->db->query('SELECT * FROM tmpay_transactions WHERE password = ? AND transaction_id = ?', $password, $transaction_id);
    if($tmpay)
    {
        $user_id_refill = $tmpay['user_id'];
        $this->db->query('UPDATE users SET point = point + ? WHERE user_id = ?', $real_amount, $user_id_refill);
        $this->db->query("UPDATE tmpay_transactions SET status = 'success' WHERE password = ? AND transaction_id = ?", $password, $transaction_id);
    }
    */
    die('SUCCEED|TOPPED_UP_THB_' . $real_amount . '_TO_' . $user_id_refill);
}
else
{
    /* เติมเงินไม่สำเร็จ */
    /* เช่น
    $this->db->query("UPDATE tmpay_transactions SET status = 'failed' WHERE password = ? AND transaction_id = ?", $password, $transaction_id);
    */
    die('ERROR|CARD_DOESNT_EXIST');
}
?>
```

---

## 3. การทดสอบระบบ TMPAY

ท่านสามารถทดสอบสคริปต์หรือโปรแกรมของท่านโดยไม่จำเป็นต้องใช้บัตรเงินสดทรูมันนี่ที่ใช้งานได้จริง โดยกำหนด `merchant_id` ให้เป็น `TEST` จากนั้นท่านสามารถทดสอบการเติมเงินด้วยรหัสบัตรเงินสดดังต่อไปนี้

| ลำดับ | รหัสบัตรเงินสด | มูลค่า |
|-------|----------------|--------|
| 1 | `55555555555551` | 50 บาท |
| 2 | `55555555555552` | 90 บาท |
| 3 | `55555555555553` | 150 บาท |
| 4 | `55555555555554` | 300 บาท |
| 5 | `55555555555555` | 500 บาท |
| 6 | `55555555555556` | 1000 บาท |
