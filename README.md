<img src="https://github.com/bymayo/craft-order-importer/blob/craft-5/resources/icon.png" width="60">

# Order Importer for Craft CMS 5

Order Importer is a Craft CMS plugin that uses [Feed Me](https://plugins.craftcms.com/feed-me) to import orders from a import file (XML & JSON).

<img src="https://raw.githubusercontent.com/bymayo/craft-order-importer/craft-5/resources/screenshot.png" width="850">

## Install

-  Install Feed Me via the plugin store [Feed Me](https://plugins.craftcms.com/feed-me) or, with Composer via `composer require craftcms/feed-me` from your project directory.
-  Install Order Importer via the plugin store [Order Importer](https://plugins.craftcms.com/order-importer) or, with Composer via `composer require bymayo/order-importer` from your project directory.
-  Enable / Install both plugins in the Craft Control Panel under `Settings > Plugins`
-  Follow the [Setup](#setup) instructions below

## Requirements

- Craft CMS 5.x
- Feed Me 3.x
- PHP 8.2
- MySQL (No PostgreSQL support)

## Setup

This plugin requires Feed Me to function. All Product/Purchasables must be created in the CMS before importing orders. Similar if all Users/Customers are created in the CMS before importing orders this would be a good idea, but this plugin will create any Users/Customers that don't exist.

1. Navigate to the Feed Me in the sidebar and create a new feed.
2. Give your feed a `Name`, add you `Feed URL`.
3. In `Feed Type` select `JSON` or `XML`. These are the only supported feed types due to how you need to structure the feed. See [Feed Types](#feed-types) for examples.
4. Select the 'Commerce Orders' `Element Type` from the dropdown.
5. If you have a custom field layout for Commerce Orders, select it from the `Custom Field Layout` dropdown.
6. All other settings are optional, hit `Save & Continue` and select your `Primary Element` from the dropdown.
7. Map your fields to the feed elements and hit `Save & Continue` again. See [Mapping](#mapping) for some important notes.
8. Hit `Process it now` and you should see your orders imported into the CMS.

## Feed Types

We recommend using only XML and JSON file types for your feeds due to how you need to structure the feed, specifically the `Line Items` and `Address` fields. Below are some examples of how to structure your feed:

### XML

```xml
<?xml version="1.0" encoding="UTF-8"?>
<orders>
    <order>
        <customerId>1</customerId>
        <orderEmailAddress>test@test.com</orderEmailAddress>
        <orderStatus>new</orderStatus>
        <number>fd20cfdf6ff2e6c30a074385b273b928</number>
        <reference>000550</reference>
        <isCompleted>true</isCompleted>
        <dateOrdered>2025-07-16 09:51:05</dateOrdered>
        <datePaid>2025-07-16 09:51:05</datePaid>
        <dateAuthorized>2025-07-30 09:54:26</dateAuthorized>
        <currency>GBP</currency>
        <paymentCurrency>GBP</paymentCurrency>
        <orderLanguage>en-GB</orderLanguage>
        <billingAddress>
            <id>1827</id>
            <firstName>First</firstName>
            <lastName>Name</lastName>
            <addressLine1>Address Line 1</addressLine1>
            <addressLine2>Address Line 2</addressLine2>
            <addressLine3>Address Line 3</addressLine3>
            <city>City</city>
            <state>State</state>
            <postalCode>12345</postalCode>
            <countryCode>US</countryCode>
        </billingAddress>
        <shippingAddress>
            <id>1827</id>
            <firstName>First</firstName>
            <lastName>Name</lastName>
            <addressLine1>Address Line 1</addressLine1>
            <addressLine2>Address Line 2</addressLine2>
            <addressLine3>Address Line 3</addressLine3>
            <city>City</city>
            <state>State</state>
            <postalCode>12345</postalCode>
            <countryCode>US</countryCode>
        </shippingAddress>
        <lineItems>
            <lineItem>
                <purchasableId>3745</purchasableId>
                <sku>ABC123</sku>
                <description>Red T-Shirt</description>
                <options>{&quot;currency&quot;:&quot;GBP&quot;,&quot;purchasableId&quot;:3745,&quot;time&quot;:1752655509,&quot;price&quot;:{&quot;base&quot;:&quot;25.0000&quot;,&quot;modifier&quot;:&quot;1&quot;,&quot;modified&quot;:25,&quot;salePrice&quot;:25,&quot;saleAmount&quot;:0}}</options>
                <width>10</width>
                <height>30</height>
                <length>20</length>
                <weight>10</weight>
                <quantity>2</quantity>
                <price>25.00</price>
                <salePrice>20.00</salePrice>
                <saleAmount>5.00</saleAmount>
                <subTotal>40.00</subTotal>
                <total>40.00</total>
                <note>Leave parcel at the front door</note>
                <privateNote>Delivery drive to leave parcel in safe place</privateNote>
                <taxCategory>1</taxCategory>
                <shippingCategory>1</shippingCategory>
            </lineItem>
        </lineItems>
        <transactions>
            <transaction>
                <id>1044</id>
                <status>success</status>
                <type>purchase</type>
                <amount>40.0000</amount>
            </transaction>
        </transactions>
        <gatewayId>1</gatewayId>
        <shippingTotal>10.00</shippingTotal>
        <shippingMethodName>DHL Next Day</shippingMethodName>
        <taxTotal>7.50</taxTotal>
        <taxRateName>VAT</taxRateName>
        <taxRate>20%</taxRate>
        <discountTotal>10.00</discountTotal>
        <discountName>10% off</discountName>
        <discountDescription>Online Offer</discountDescription>
    </order>
</orders>
```

### JSON

```json
{
  "order": {
    "customerId": "1",
    "orderEmailAddress": "test@test.com",
    "orderStatus": "new",
    "number": "fd20cfdf6ff2e6c30a074385b273b928",
    "reference": "000550",
    "isCompleted": "true",
    "dateOrdered": "2025-07-16 09:51:05",
    "datePaid": "2025-07-16 09:51:05",
    "dateAuthorized": "2025-07-30 09:54:26",
    "currency": "GBP",
    "paymentCurrency": "GBP",
    "orderLanguage": "en-GB",
    "billingAddress": {
      "id": "1827",
      "firstName": "First",
      "lastName": "Name",
      "addressLine1": "Address Line 1",
      "addressLine2": "Address Line 2",
      "addressLine3": "Address Line 3",
      "city": "City",
      "state": "State",
      "postalCode": "12345",
      "countryCode": "US"
    },
    "shippingAddress": {
      "id": "1827",
      "firstName": "First",
      "lastName": "Name",
      "addressLine1": "Address Line 1",
      "addressLine2": "Address Line 2",
      "addressLine3": "Address Line 3",
      "city": "City",
      "state": "State",
      "postalCode": "12345",
      "countryCode": "US"
    },
    "lineItems": {
      "lineItem": {
        "purchasableId": "3745",
        "sku": "ABC123",
        "description": "Red T-Shirt",
        "options": "{\"currency\":\"GBP\",\"purchasableId\":3745,\"time\":1752655509,\"price\":{\"base\":\"25.0000\",\"modifier\":\"1\",\"modified\":25,\"salePrice\":25,\"saleAmount\":0}}",
        "width": "10",
        "height": "30",
        "length": "20",
        "weight": "10",
        "quantity": "2",
        "price": "25.00",
        "salePrice": "20.00",
        "saleAmount": "5.00",
        "subTotal": "40.00",
        "total": "40.00",
        "note": "Leave parcel at the front door",
        "privateNote": "Delivery drive to leave parcel in safe place",
        "taxCategory": "1",
        "shippingCategory": "1"
      }
    },
    "transactions": {
      "transaction": {
        "id": "1044",
        "status": "success",
        "type": "purchase",
        "amount": "40.0000"
      }
    },
    "gatewayId": "1",
    "shippingTotal": "10.00",
    "shippingMethodName": "DHL Next Day",
    "taxTotal": "7.50",
    "taxRateName": "VAT",
    "taxRate": "20%",
    "discountTotal": "10.00",
    "discountName": "10% off",
    "discountDescription": "Online Offer"
  }
}
```

There are some examples of how to create XML feeds in the repository under `examples/` if you're importing from a Craft 4 or 5 project with the variables already set up.

## Mapping 

1. It's important that Products/Purchasables are created in the CMS before importing orders to use the `Purchasable ID` fields correctly.
2. Similarly, it's important that Users/Customers are created in the CMS before importing orders to use the `Customer ID` fields correctly. But the plugin will create any Users/Customers that don't exist.
3. Make sure you structure your `Line Items` as an Array, so it can loop through the array and create the line items.
4. Make sure you structure your `Transactions` as an Array, so it can loop through the array and create the transactions.
5. `Billing Address Fields` and `Shipping Address Fields` need to also be setup line an Array.
6. Line Item `Options` need to be setup as a valid JSON string, or not imported at all.
7. There are no "Total" fields to include in your feeds, due to the way Craft Commerce calculates all totals.

## Caveats

- Currently you can only add one Transaction per order.
- Currently multiple Shipping, Tax and Discount adjusters are not supported.

## Support

If you have any issues (Surely not!) then I'll aim to reply to these as soon as possible. If it's a site-breaking-oh-no-what-has-happened moment, then hit me up on the Craft CMS Discord - @bymayo

## Roadmap

- Add support multiple Shipping, Tax and Discount adjusters.
- Add support multiple Transactions per order.
- Add support for Status History