<?php

declare(strict_types=1);

namespace Seventhings\Models\Enums;

enum FieldTypeName: string
{
    case Attachment = 'ATTACHMENT';
    case Barcode = 'BARCODE';
    case Boolean = 'BOOLEAN';
    case Coordinates = 'COORDINATES';
    case Date = 'DATE';
    case Datetime = 'DATETIME';
    case Decimal = 'DECIMAL';
    case Dropdown = 'DROPDOWN';
    case FieldValueComparison = 'FIELD_VALUE_COMPARISON';
    case Link = 'LINK';
    case LinkedAssets = 'LINKED_ASSETS';
    case LinkedLocation = 'LINKED_LOCATION';
    case LinkedPerson = 'LINKED_PERSON';
    case LinkedRoom = 'LINKED_ROOM';
    case LinkedUser = 'LINKED_USER';
    case LongText = 'LONG_TEXT';
    case Money = 'MONEY';
    case Number = 'NUMBER';
    case Reminder = 'REMINDER';
    case Text = 'TEXT';
}
