<?php

declare(strict_types=1);

namespace Module\Users\Enums;

/**
 * Машиночитаемые коды ошибок модуля Users, попадающие в поле
 * errors[].code JSON:API-ответа.
 */
enum UserErrorCode: string
{
    case UserNotFound = 'USER_NOT_FOUND';
    case SystemUserImmutable = 'SYSTEM_USER_IMMUTABLE';
    case SelfDeleteForbidden = 'SELF_DELETE_FORBIDDEN';
    case LastAdministrator = 'LAST_ADMINISTRATOR';
    case NoProjectContext = 'NO_PROJECT_CONTEXT';

    case RoleNotFound = 'ROLE_NOT_FOUND';
    case SystemRoleImmutable = 'SYSTEM_ROLE_IMMUTABLE';
    case RoleInUse = 'ROLE_IN_USE';

    case ManageAdminForbidden = 'MANAGE_ADMIN_FORBIDDEN';
    case ManagePrivilegedForbidden = 'MANAGE_PRIVILEGED_FORBIDDEN';
    case RoleAssignForbidden = 'ROLE_ASSIGN_FORBIDDEN';
    case RoleEscalationForbidden = 'ROLE_ESCALATION_FORBIDDEN';
    case PermissionEscalationForbidden = 'PERMISSION_ESCALATION_FORBIDDEN';
    case RoleManageForbidden = 'ROLE_MANAGE_FORBIDDEN';

    public function label(): string
    {
        return match ($this) {
            self::UserNotFound => 'Пользователь не найден',
            self::SystemUserImmutable => 'Системного пользователя нельзя изменить',
            self::SelfDeleteForbidden => 'Нельзя удалить текущего пользователя',
            self::LastAdministrator => 'В проекте должен остаться администратор',
            self::NoProjectContext => 'Контекст проекта не определён',
            self::RoleNotFound => 'Роль не найдена',
            self::SystemRoleImmutable => 'Системную роль нельзя изменить',
            self::RoleInUse => 'Роль назначена пользователям',
            self::ManageAdminForbidden => 'Нельзя управлять администратором',
            self::ManagePrivilegedForbidden => 'Нельзя управлять более привилегированным пользователем',
            self::RoleAssignForbidden => 'Недостаточно прав для назначения ролей',
            self::RoleEscalationForbidden => 'Нельзя назначить роль выше полномочий',
            self::PermissionEscalationForbidden => 'Нельзя выдать разрешения выше полномочий',
            self::RoleManageForbidden => 'Нельзя изменять роль выше полномочий',
        };
    }
}
