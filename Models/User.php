<?php

namespace Aurora\Modules\Core\Models;

use \Aurora\System\Classes\Model;
class User extends Model
{
    protected $moduleName = 'Core';

    protected $primaryKey = 'Id';

    protected $parentType = Tenant::class;

    protected $parentKey = 'IdTenant';

    protected $parentInheritedAttributes = [
        'Files::UserSpaceLimitMb'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'Name',
        'PublicId',
        'IdTenant',
        'IsDisabled',
        'IdSubscription',
        'Role',
        'DateCreated',
        'LastLogin',
        'LastLoginNow',
        'LoginsCount',
        'Language',
        'TimeFormat',
        'DateFormat',
        'Question1',
        'Question2',
        'Answer1',
        'Answer2',
        'SipEnable',
        'SipImpi',
        'SipPassword',
        'DesktopNotifications',
        'Capa',
        'CustomFields',
        'FilesEnable',
        'EmailNotification',
        'PasswordResetHash',
        'WriteSeparateLog',
        'DefaultTimeZone',
        'TokensValidFromTimestamp',
        'Properties'
    ];

    /**
    * The attributes that should be hidden for arrays.
    *
    * @var array
    */
    protected $hidden = [
    ];

    protected $casts = [
        'Properties' => 'array',
    ];

    protected $attributes = [
    ];
}