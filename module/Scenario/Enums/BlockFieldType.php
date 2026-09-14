<?php

declare(strict_types=1);

namespace Module\Scenario\Enums;

enum BlockFieldType: string
{
    case Input = 'input';
    case Email = 'email';
    case Phone = 'phone';
    case Vin = 'vin';
    case Grz = 'grz';
    case Textarea = 'textarea';
    case RichText = 'rich_text';
    case Number = 'number';
    case Select = 'select';
    case Date = 'date';
    case Datetime = 'datetime';
    case Checkbox = 'checkbox';
    case Hidden = 'hidden';
    case Collapse = 'collapse';
    case Action = 'action';
    case ActionList = 'action_list';
    case DirectoryList = 'directory_list';
    case DirectoryTree = 'directory_tree';
    case DirectoryTable = 'directory_table';
    case Suggest = 'suggest';
    case MapPoint = 'map_point';
    case Route = 'route';
    case DirectoryMap = 'directory_map';
}
