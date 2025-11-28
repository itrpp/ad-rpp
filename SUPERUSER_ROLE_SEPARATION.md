# Superuser Role Separation Implementation

## Overview
Updated `PermissionManager.php` to ensure superusers are treated as a distinct role from admins, with their own specific permissions and menu visibility rules.

## Key Changes Made

### 1. Updated Permission Logic (`hasPermission()`)
- **Before**: Superusers could inherit admin permissions through LDAP group checks
- **After**: Superusers have explicitly limited permissions - only view permissions
- **Implementation**: Added explicit permission check for superusers before admin checks

```php
// Superusers have limited permissions - only view permissions
if ($this->isSuperUser()) {
    $superUserPermissions = [
        self::PERMISSION_AD_USER_VIEW,
        self::PERMISSION_LDAP_USER_VIEW,
    ];
    return in_array($permission, $superUserPermissions);
}
```

### 2. Added Role Distinction Methods
- **`isSuperUserOnly()`**: Checks if user is superuser but NOT admin
- **`getCurrentUserRole()`**: Returns the current user's assigned role name for debugging

### 3. Enhanced Role Assignment Logic
- **Priority-based assignment**: Admin first, then Superuser, then Regular User
- **Debug logging**: Added logging to track role assignments
- **Clear separation**: Superusers cannot inherit admin privileges

### 4. Updated RBAC Permission Assignment
- **Superuser permissions**: Only `adUserView` and `ldapUserView`
- **Admin permissions**: All permissions (create, update, delete, view)
- **Regular user permissions**: Only view permissions

## Current RBAC Configuration

### Roles Created:
- **admin**: Full permissions (all CRUD operations)
- **superuser**: View-only permissions (`adUserView`, `ldapUserView`)
- **user**: View-only permissions (`adUserView`, `ldapUserView`)
- **guest**: No permissions

### LDAP Group Mapping:
- **Admin Groups**: `CN=manage Ad_admin,CN=User,DC=rpphosp,DC=local`
- **Superuser Groups**: `CN=manage Ad_user,CN=User,DC=rpphosp,DC=local`

## Menu Visibility Rules

### Admin-Only Menus:
- User Registration (`ldapuser/ou-register`)
- All management functions

### Admin + Superuser Menus:
- Manage All User (`ldapuser/ou-user`) - View access only
- View buttons in action columns

### Superuser Limitations:
- Cannot create new users
- Cannot update existing users
- Cannot delete users
- Cannot access registration functions
- Can only view user data

## Testing Recommendations

1. **Login as superuser** (member of `CN=manage Ad_user,CN=User,DC=rpphosp,DC=local`)
2. **Verify menu visibility**:
   - Should see "Manage All User" menu
   - Should NOT see "UserRegister" menu
   - Should NOT see "Register New Account" menu
3. **Verify page access**:
   - Can access `ou-user.php` page
   - Can see "View" buttons
   - Cannot see "Edit", "Move", "Toggle Status" buttons
4. **Verify permissions**:
   - `hasPermission(PERMISSION_LDAP_USER_VIEW)` should return `true`
   - `hasPermission(PERMISSION_LDAP_USER_CREATE)` should return `false`
   - `isSuperUserOnly()` should return `true`
   - `isLdapAdmin()` should return `false`

## Debug Information

The system now includes comprehensive debug logging:
- Role assignment process
- Permission checks
- LDAP group detection
- User status verification

Check Yii debug logs for detailed permission flow information.

## Files Modified

- `common/components/PermissionManager.php`: Core permission logic updates
- RBAC system reinitialized with proper role separation

## Next Steps

1. Test with actual superuser accounts
2. Verify menu visibility in `main.php` and `index.php`
3. Confirm page access restrictions in `ou-user.php`
4. Monitor debug logs for permission flow verification
