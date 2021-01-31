<?php
$menus = $subArray = array();

// Dashboard
// $subArray = array();
// $subArray['title'] = 'Dashboard';
// $subArray['role_id'] = [1,4];
// $subArray['api'] = 'dashboard';
// $subArray['route'] = '/admin/dashboard';
// $subArray['icon_class'] = 'fa fa-fw fa-dashboard';
// $menus[] = $subArray;

$fieldList = array();
$subArray = array();
$subArray['title'] = 'Brands';
$subArray['role_id'] = [1];
$subArray['query'] = 'employer';
$subArray['route'] = '/admin/actions/brands';
$subArray['api'] = '/admin/users';
$subArray['icon_class'] = 'fa fa-users';
$field = array();
$field['name'] = 'id';
$field['label'] = 'ID';
$field['add'] = false;
$field['list'] = false;
$field['edit'] = false;
$field['view'] = false;
$fieldList[] = $field;

$field = array();
$field['name'] = 'first_name';
$field['label'] = 'Name';
$field['type'] = 'text';
$field['add'] = true;
$field['list'] = true;
$field['edit'] = true;
$field['view'] = true;
$fieldList[] = $field;

$field = array();
$field['name'] = 'username';
$field['label'] = 'Username';
$field['type'] = 'text';
$field['add'] = true;
$field['list'] = true;
$field['edit'] = false;
$field['view'] = true;
$fieldList[] = $field;

$field = array();
$field['name'] = 'email';
$field['label'] = 'Email';
$field['type'] = 'text';
$field['add'] = true;
$field['list'] = true;
$field['edit'] = true;
$field['view'] = true;
$fieldList[] = $field;

$field = array();
$field['name'] = 'image';
$field['label'] = 'Image';
$field['is_required'] = true;
$field['add'] = true;
$field['list'] = false;
$field['edit'] = true;
$field['view'] = true;
$field['value'] = '';
$field['class'] = '';
$field['type'] = 'file';
$fieldList[] = $field;
//actions
$field = array();
$field['name'] = 'actions';
$field['label'] = 'Actions';
$field['listActions'] = array();

$action = array();
$action['api'] = '/company/edit';
$action['label'] = 'Edit';
$action['class'] = 'btn crud-action btn-primary';
$action['icon'] = 'fa fa-pencil';
$field['listActions'][] = $action;

$action = array();
$action['api'] = '/company/view';
$action['label'] = 'view';
$action['class'] = 'btn crud-action btn-info';
$action['icon'] = 'fa fa-eye';
$field['listActions'][] = $action;

$field['list'] = true;
$fieldList[] = $field;
$subArray['listview']['fields'] = $fieldList;

$filter = array();
$filter['name'] = 'q';
$filter['label'] = 'Search';
$subArray['filters'][] = $filter;
$subArray['add'] = [];
$subArray['add']['url'] = '/admin/company/add';
$menus[] = $subArray;

$subArray = array();
$subArray['role_id'] = [1];
$subArray['title'] = 'Themes';
$subArray['route'] = '/admin/actions/theme';
$subArray['api'] = '/admin/theme';
$subArray['icon_class'] = 'fa fa-users';
$fieldList = array();
$field = array();
$field['name'] = 'id';
$field['label'] = 'ID';
$field['add'] = false;
$field['list'] = false;
$field['edit'] = false;
$field['view'] = false;
$fieldList[] = $field;

$field = array();
$field['name'] = 'name';
$field['label'] = 'Name';
$field['type'] = 'text';
$field['add'] = true;
$field['list'] = true;
$field['edit'] = true;
$field['view'] = true;
$fieldList[] = $field;

$field = array();
$field['name'] = 'image';
$field['label'] = 'Image';
$field['is_required'] = true;
$field['add'] = true;
$field['list'] = false;
$field['edit'] = true;
$field['view'] = true;
$field['value'] = '';
$field['class'] = '';
$field['type'] = 'file';
$fieldList[] = $field;

//actions
$field = array();
$field['name'] = 'actions';
$field['label'] = 'Actions';
$field['listActions'] = array();

$action = array();
$action['api'] = '/theme/edit';
$action['label'] = 'Edit';
$action['class'] = 'btn crud-action btn-primary';
$action['icon'] = 'fa fa-pencil';
$field['listActions'][] = $action;

$action = array();
$action['api'] = '/theme/view';
$action['label'] = 'view';
$action['class'] = 'btn crud-action btn-info';
$action['icon'] = 'fa fa-eye';
$field['listActions'][] = $action;

$field['list'] = true;
$fieldList[] = $field;
$subArray['listview']['fields'] = $fieldList;

$filter = array();
$filter['name'] = 'q';
$filter['label'] = 'Search';
$subArray['filters'][] = $filter;
$subArray['add'] = [];
$subArray['add']['url'] = '/admin/theme/add';
$menus[] = $subArray;

$subArray = array();
$subArray['title'] = 'Restaurants';
$subArray['role_id'] = [1,4,3];
$subArray['api'] = 'restaurants';
$subArray['route'] = '/admin/restaurants';
$subArray['icon_class'] = 'fa fa-fw fa-dashboard';
$menus[] = $subArray;

$subArray = array();
$fieldList = array();
$subArray['title'] = 'Time Slot';
$subArray['role_id'] = [3,4];
$subArray['route'] = '/admin/time_slot';
$subArray['icon_class'] = 'fa fa-users';
$menus[] = $subArray;

$subArray = array();
$fieldList = array();
$subArray['title'] = 'Custom Time Slot';
$subArray['role_id'] = [3,4];
$subArray['route'] = '/admin/custom_time_slot';
$subArray['icon_class'] = 'fa fa-users';
$menus[] = $subArray;

$fieldList = array();
$subArray = array();
$subArray['title'] = 'Bookings';
$subArray['role_id'] = [1,4,3];
$subArray['route'] = '/admin/actions/bookings';
$subArray['api'] = '/bookings';
$subArray['icon_class'] = 'fa fa-users';
$field = array();
$field['name'] = 'id';
$field['label'] = 'ID';
$field['add'] = false;
$field['list'] = false;
$field['edit'] = false;
$field['view'] = false;
$fieldList[] = $field;

$field = array();
$field['name'] = 'user.first_name';
$field['label'] = 'Name';
$field['type'] = 'text';
$field['add'] = false;
$field['list'] = true;
$field['edit'] = false;
$field['view'] = false;
$fieldList[] = $field;

$field = array();
$field['name'] = 'reg_date';
$field['label'] = 'Date';
$field['type'] = 'text';
$field['add'] = true;
$field['list'] = true;
$field['edit'] = false;
$field['view'] = false;
$fieldList[] = $field;

$field = array();
$field['name'] = 'from_timeslot';
$field['label'] = 'Slot';
$field['type'] = 'text';
$field['add'] = true;
$field['list'] = true;
$field['edit'] = false;
$field['view'] = false;
$fieldList[] = $field;

$field = array();
$field['name'] = 'max_person';
$field['label'] = 'Persons';
$field['type'] = 'text';
$field['add'] = true;
$field['list'] = true;
$field['edit'] = false;
$field['view'] = false;
$fieldList[] = $field;

$field = array();
$field['name'] = 'offer_percentage';
$field['label'] = 'Discount %';
$field['type'] = 'text';
$field['add'] = true;
$field['list'] = true;
$field['edit'] = false;
$field['view'] = false;
$fieldList[] = $field;

$field = array();
$field['name'] = 'status';
$field['label'] = 'Status';
$field['type'] = 'select';
$field['id_fill'] = true;
$field['is_required'] = true;
$field['value'] = array();
$field['options'] = array(
	array(
		'id' => 0,
		'name' => 'Booked'
	),
	array(
		'id' => 1,
		'name' => 'Arrived'
	),
	array(
		'id' => 2,
		'name' => 'Canceled'
	),
);
$field['add'] = false;
$field['list'] = false;
$field['edit'] = true;
$field['view'] = false;
$fieldList[] = $field;

//actions
$field = array();
$field['name'] = 'actions';
$field['label'] = 'Actions';
$field['listActions'] = array();

$action = array();
$action['api'] = '/bookings/edit';
$action['label'] = 'Edit';
$action['class'] = 'btn crud-action btn-primary';
$action['icon'] = 'fa fa-pencil';
$field['listActions'][] = $action;

$field['list'] = true;
$fieldList[] = $field;
$subArray['listview']['fields'] = $fieldList;

$subArray['add']['url'] = '/admin/bookings/add';
$menus[] = $subArray;

// Master
$fieldList = array();
$subArray = array();
$subArray['title'] = 'Master';
$subArray['role_id'] = [1];
$subArray['icon_class'] = 'fa fa-users';
$subArray['listview']['fields'] = array();

$fieldList = array();
$childMenu = array();
$childMenu['role_id'] = [1];
$childMenu['title'] = 'Email Templates';
$childMenu['api'] = '/admin/email_templates';
$childMenu['route'] = '/admin/actions/email_templates';
$childMenu['icon_class'] = 'fa fa-users';
//actions
$field = array();
$field['name'] = 'actions';
$field['label'] = 'Actions';
$field['listActions'] = array();

$action = array();
$action['api'] = '/admin/email_templates';
$action['label'] = 'Edit';
$action['class'] = 'btn crud-action btn-primary';
$action['icon'] = 'fa fa-pencil';
$field['listActions'][] = $action;
$field['list'] = true;
$fieldList[] = $field;

$field = array();
$field['name'] = 'display_name';
$field['label'] = 'Name';
$field['type'] = 'text';
$field['is_required'] = true;
$field['list'] = true;
$field['edit'] = true;
$field['view'] = true;
$fieldList[] = $field;

$field = array();
$field['name'] = 'subject';
$field['label'] = 'Subject';
$field['type'] = 'text';
$field['is_required'] = true;
$field['edit'] = true;
$field['list'] = false;
$field['view'] = true;
$fieldList[] = $field;

$field = array();
$field['name'] = 'description';
$field['label'] = 'Description';
$field['type'] = 'text';
$field['is_required'] = true;
$field['edit'] = true;
$field['list'] = false;
$field['view'] = true;
$fieldList[] = $field;

$field = array();
$field['name'] = 'html_email_content';
$field['label'] = 'Content';
$field['type'] = 'smart_editor';
$field['is_required'] = true;
$field['edit'] = true;
$field['list'] = false;
$field['view'] = false;
$fieldList[] = $field;

$childMenu['listview']['fields'] = $fieldList;
$subArray['child_sub_menu'][] = $childMenu;
$fieldList = array();
$childMenu = array();
$childMenu['title'] = 'Pages';
$childMenu['role_id'] = [1];
$childMenu['api'] = '/admin/pages';
$childMenu['route'] = '/admin/actions/pages';
$childMenu['icon_class'] = 'fa fa-users';
//actions
$field = array();
$field['name'] = 'actions';
$field['label'] = 'Actions';
$field['listActions'] = array();

$action = array();
$action['api'] = '/admin/pages';
$action['label'] = 'Edit';
$action['class'] = 'btn crud-action btn-primary';
$action['icon'] = 'fa fa-pencil';
$field['list'] = true;
$field['edit'] = false;
$field['listActions'][] = $action;
$field['list'] = true;
$fieldList[] = $field;

$field = array();
$field['name'] = 'title';
$field['label'] = 'Title';
$field['type'] = 'text';
$field['is_required'] = true;
$field['list'] = true;
$field['edit'] = true;
$field['view'] = true;
$fieldList[] = $field;

$field = array();
$field['name'] = 'dispaly_url';
$field['label'] = 'Url';
$field['type'] = 'text';
$field['is_required'] = true;
$field['list'] = false;
$field['edit'] = true;
$fieldList[] = $field;

$field = array();
$field['name'] = 'content';
$field['label'] = 'Content';
$field['type'] = 'smart_editor';
$field['is_required'] = true;
$field['list'] = false;
$field['edit'] = true;
$fieldList[] = $field;

$childMenu['listview']['fields'] = $fieldList;
$subArray['child_sub_menu'][] = $childMenu;
$menus[] = $subArray;

// Settings
$fieldList = array();
$subArray = array();
$subArray['title'] = 'Settings';
$subArray['role_id'] = [1];
$subArray['api'] = '/admin/settings';
$subArray['route'] = '/admin/actions/settings';
$subArray['icon_class'] = 'fa fa-users';

$field = array();
$field['name'] = 'actions';
$field['label'] = 'Actions';
$field['listActions'] = array();

$action = array();
$action['api'] = '/admin/settings';
$action['label'] = 'Edit';
$action['class'] = 'btn crud-action btn-primary';
$action['icon'] = 'fa fa-pencil';
$field['listActions'][] = $action;
$field['list'] = true;
$fieldList[] = $field;

$field = array();
$field['name'] = 'name';
$field['label'] = 'Name';
$field['list'] = true;
$field['view'] = true;
$fieldList[] = $field;

$field = array();
$field['name'] = 'description';
$field['label'] = 'Description';
$field['list'] = true;
$field['view'] = true;
$fieldList[] = $field;

$subArray['listview']['fields'] = $fieldList;
$menus[] = $subArray;

$subArray = array();
$subArray['title'] = 'Change Password';
$subArray['role_id'] = [1,4,3];
$subArray['api'] = '/users/change_password';
$subArray['route'] = '/admin/change_password';
$subArray['icon_class'] = 'fa fa-fw fa-dashboard';
$menus[] = $subArray;

// Logout
$subArray = array();
$subArray['title'] = 'Logout';
$subArray['api'] = '/logout';
$subArray['route'] = '/admin/actions/logout';
$subArray['icon_class'] = 'fa fa-users';
$menus[] = $subArray;
