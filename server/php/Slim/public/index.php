<?php
/**
 * Base API
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Base
 * @subpackage Core
 * https://www.oreilly.com/library/view/paypal-apis-up/9781449321666/ch04.html
 */
require_once '../lib/bootstrap.php';
use Illuminate\Database\Capsule\Manager as Capsule;
date_default_timezone_set(SITE_TIMEZONE);
$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

$app->add(function ($req, $res, $next) {
    $response = $next($req, $res);
    return $response
            ->withHeader('Access-Control-Allow-Origin', 'http://mysite')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});
/**
 * GET oauthGet
 * Summary: Get site token
 * Notes: oauth
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/oauth/token', function ($request, $response, $args) {
    $post_val = array(
        'grant_type' => 'client_credentials',
        'client_id' => OAUTH_CLIENT_ID,
        'client_secret' => OAUTH_CLIENT_SECRET
    );
    $response = getToken($post_val);
    return renderWithJson($response);
});
/**
 * GET oauthRefreshTokenGet
 * Summary: Get site refresh token
 * Notes: oauth
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/oauth/refresh_token', function ($request, $response, $args) {
    $post_val = array(
        'grant_type' => 'refresh_token',
        'refresh_token' => $_GET['token'],
        'client_id' => OAUTH_CLIENT_ID,
        'client_secret' => OAUTH_CLIENT_SECRET
    );
    $response = getToken($post_val);
    if (!empty($response) && $response['access_token'] != '') {
		return renderWithJson($response);
	} else {
		return renderWithJson(array(), 'Session Expired.', '', 1);
	}
});
/**
 * POST usersRegisterPost
 * Summary: new user
 * Notes: Post new user.
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/users/register', function ($request, $response, $args) {
    global $_server_domain_url;
    $args = $request->getParsedBody();
    $result = array();
    $user = new Models\User;
    $validationErrorFields = $user->validate($args);
    if (!empty($validationErrorFields)) {
        $validationErrorFields = $validationErrorFields->toArray();
    }
    if (checkAlreadyUsernameExists($args['username']) && empty($validationErrorFields)) {
        $validationErrorFields['unique'] = array();
        array_push($validationErrorFields['unique'], 'username');
    }
    if (checkAlreadyEmailExists($args['email']) && empty($validationErrorFields)) {
        $validationErrorFields['unique'] = array();
        array_push($validationErrorFields['unique'], 'email');
    }
    if (empty($validationErrorFields['unique'])) {
        unset($validationErrorFields['unique']);
    }
    if (empty($validationErrorFields['required'])) {
        unset($validationErrorFields['required']);
    }
    if (empty($validationErrorFields)) {
        foreach ($args as $key => $arg) {
            if ($key == 'password') {
                $user->{$key} = getCryptHash($arg);
            } else {
                $user->{$key} = $arg;
            }
        }
        try {
            $user->is_email_confirmed = (USER_IS_EMAIL_VERIFICATION_FOR_REGISTER == 1) ? 0 : 1;
            $user->is_active = (USER_IS_ADMIN_ACTIVATE_AFTER_REGISTER == 1) ? 0 : 1;
            if (USER_IS_AUTO_LOGIN_AFTER_REGISTER == 1) {
                $user->is_email_confirmed = 1;
                $user->is_active = 1;
            }
            $user->role_id = \Constants\ConstUserTypes::User;
            $user->save();
			offlineToCart($user->id);
            if (!empty($args['image'])) {
                saveImage('UserAvatar', $args['image'], $user->id);
            }
            if (!empty($args['cover_photo'])) {
                saveImage('CoverPhoto', $args['cover_photo'], $user->id);
            }
            // send to admin mail if USER_IS_ADMIN_MAIL_AFTER_REGISTER is true
            if (USER_IS_ADMIN_MAIL_AFTER_REGISTER == 1) {
                $emailFindReplace = array(
                    '##USERNAME##' => $user->username,
                    '##USEREMAIL##' => $user->email,
                    '##SUPPORT_EMAIL##' => SUPPORT_EMAIL
                );
                sendMail('newuserjoin', $emailFindReplace, SITE_CONTACT_EMAIL);
            }
            if (USER_IS_WELCOME_MAIL_AFTER_REGISTER == 1) {
                $emailFindReplace = array(
                    '##USERNAME##' => $user->username,
                    '##SUPPORT_EMAIL##' => SUPPORT_EMAIL
                );
                // send welcome mail to user if USER_IS_WELCOME_MAIL_AFTER_REGISTER is true
                sendMail('welcomemail', $emailFindReplace, $user->email);
            }
            if (USER_IS_EMAIL_VERIFICATION_FOR_REGISTER == 1) {
                $emailFindReplace = array(
                    '##USERNAME##' => $user->username,
                    '##ACTIVATION_URL##' => $_server_domain_url . '/activation/' . $user->id . '/' . md5($user->username)
                );
                sendMail('activationrequest', $emailFindReplace, $user->email);
            }
            if (USER_IS_AUTO_LOGIN_AFTER_REGISTER == 1) {
                $scopes = '';
				if($user->role_id == \Constants\ConstUserTypes::Admin) {
					$scopes = 'canAdmin';
				} else if($user->role_id == \Constants\ConstUserTypes::Employer) {
					$scopes = 'canContestantUser';
				} else {
					$scopes = 'canUser';	
				}
                $post_val = array(
                    'grant_type' => 'password',
                    'username' => $user->username,
                    'password' => $user->password,
                    'client_id' => OAUTH_CLIENT_ID,
                    'client_secret' => OAUTH_CLIENT_SECRET,
                    'scope' => $scopes
                );
                $response = getToken($post_val);
				$enabledIncludes = array(
                    'attachment',
                    // 'cover_photo',
					'address',
					'role'
                );
                $userData = Models\User::with($enabledIncludes)->find($user->id);
                $result = $response + $userData->toArray();
            } else {
                $enabledIncludes = array(
                    'attachment',
                    // 'cover_photo',
					'address',
					'role'
                );
                $user = Models\User::with($enabledIncludes)->find($user->id);
                $result = $user->toArray();
            }
            return renderWithJson($result, 'Success','', 0);
        } catch (Exception $e) {
			return renderWithJson($result, 'User could not be added. Please, try again.', '', 1);
        }
    } else {
		if (!empty($validationErrorFields)) {
			foreach ($validationErrorFields as $key=>$value) {
				if ($key == 'unique') {
					return renderWithJson($result, ucfirst($value[0]).' already exists. Please, try again login.', '', 1);
				} else if (!empty($value[0]) && !empty($value[0]['numeric'])) {
					return renderWithJson($result, $value[0]['numeric'], '', 1);
				} else {
					return renderWithJson($result, $value[0], '', 1);
				}
				break;
			}
		} else {
			return renderWithJson($result, 'User could not be added. Please, try again.', $validationErrorFields, 1);
		}
    }
});
/**
 * PUT usersUserIdActivationHashPut
 * Summary: User activation
 * Notes: Send activation hash code to user for activation. \n
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/users/activation/{userId}/{hash}', function ($request, $response, $args) {
    $result = array();
    $user = Models\User::where('id', $request->getAttribute('userId'))->first();
    if (!empty($user)) {
        if($user->is_email_confirmed != 1) {
            if (md5($user['username']) == $request->getAttribute('hash')) {
                $user->is_email_confirmed = 1;
                $user->is_active = (USER_IS_ADMIN_ACTIVATE_AFTER_REGISTER == 0 || USER_IS_AUTO_LOGIN_AFTER_REGISTER == 1) ? 1 : 0;
                $user->save();
                if (USER_IS_AUTO_LOGIN_AFTER_REGISTER == 1) {
                    $scopes = '';
                    if (isset($user->role_id) && $user->role_id == \Constants\ConstUserTypes::User) {
                        $scopes = implode(' ', $user['user_scopes']);
                    } else {
                        $scopes = '';
                    }
                    $post_val = array(
                        'grant_type' => 'password',
                        'username' => $user->username,
                        'password' => $user->password,
                        'client_id' => OAUTH_CLIENT_ID,
                        'client_secret' => OAUTH_CLIENT_SECRET,
                        'scope' => $scopes
                    );
                    $response = getToken($post_val);
                    $result['data'] = $response + $user->toArray();
                } else {
                    $result['data'] = $user->toArray();
                }
                return renderWithJson($result, 'Success','', 0);
            } else {
                return renderWithJson($result, 'Invalid user deatails.', '', 1);
            }
        } else {
            return renderWithJson($result, 'Invalid Request', '', 1);
        }
    } else {
        return renderWithJson($result, 'Invalid user deatails.', '', 1);
    }
});
/**
 * POST usersLoginPost
 * Summary: User login
 * Notes: User login information post
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/users/login', function ($request, $response, $args) {
    $body = $request->getParsedBody();
	$result = array();
	$user = new Models\User;
	$enabledIncludes = array(
		'attachment',
		'role',
		'address'
	);		
	if (USER_USING_TO_LOGIN == 'username') {
		if (isset($body['role_id']) && $body['role_id'] != '') {
			$log_user = $user->where('username', $body['username'])->with($enabledIncludes)->where('is_active', 1)->where('is_email_confirmed', 1)->where('role_id', $body['role_id'])->first();
		} else {
			$log_user = $user->where('username', $body['username'])->with($enabledIncludes)->where('is_active', 1)->where('is_email_confirmed', 1)->first();
		}
	} else {
		$log_user = $user->where('email', $body['email'])->with($enabledIncludes)->where('is_active', 1)->where('is_email_confirmed', 1)->where('role_id', $body['role_id'])->first();
	}
	$password = crypt($body['password'], $log_user['password']);
	$validationErrorFields = $user->validate($body);
	$validationErrorFields = array();
	if (empty($validationErrorFields) && !empty($log_user) && ($password == $log_user['password'])) {
		$scopes = '';
		if($log_user['role']['id'] == \Constants\ConstUserTypes::Admin) {
			$scopes = 'canAdmin';
		} else if($log_user['role']['id'] == \Constants\ConstUserTypes::Employer) {
			$scopes = 'canContestantUser';
		} else if($log_user['role']['id'] == \Constants\ConstUserTypes::Company) {
			$scopes = 'canCompanyUser';
		} else {
			$scopes = 'canUser';	
		}
		$post_val = array(
			'grant_type' => 'password',
			'username' => $log_user['username'],
			'password' => $password,
			'client_id' => OAUTH_CLIENT_ID,
			'client_secret' => OAUTH_CLIENT_SECRET,
			'scope' => $scopes
		);
		$response = getToken($post_val);
		if (!empty($response['refresh_token'])) {
			$log_user->makeVisible(['subscription_end_date']);
			$log_user->is_subscribed = ($log_user->subscription_end_date && strtotime($log_user->subscription_end_date) >= strtotime(date('Y-m-d'))) ? true : false;			
			$result = $response + $log_user->toArray();
			$userLogin = new Models\UserLogin;
			$userLogin->user_id = $log_user->id;
			$userLogin->ip_id = saveIp();
			$userLogin->user_agent = $_SERVER['HTTP_USER_AGENT'];
			$userLogin->save();
			offlineToCart($log_user->id);
			$result['cart_count'] = Models\Cart::where('is_purchase', false)->where('user_id', $userLogin->user_id)->count();
			return renderWithJson($result, 'LoggedIn Successfully');
		} else {
			return renderWithJson($result, 'Your login credentials are invalid.', '', 1);
		}
	} else {
		return renderWithJson($result, 'Your login credentials are invalid.', $validationErrorFields, 1);
	}
});
/**
 * Get userSocialLoginGet
 * Summary: Social Login for twitter
 * Notes: Social Login for twitter
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/users/social_login', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    if (!empty($queryParams['type'])) {
        $response = social_auth_login($queryParams['type']);
		return renderWithJson($response);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
});
/**
 * POST userSocialLoginPost
 * Summary: User Social Login
 * Notes:  Social Login
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/users/social_login', function ($request, $response, $args) {
    $body = $request->getParsedBody();
	try {
		$result = array();
		if (!empty($_GET['type'])) {
			$response = social_auth_login($_GET['type'], $body);
			// return (($response && $response['error'] && $response['error']['code'] == 1) ? renderWithJson($response) : renderWithJson($result, 'Unable to fetch details', '', 1));
			// $response['cart_count'] = Models\Cart::where('is_purchase', false)->where('user_id', $response['id'])->count();
			return renderWithJson($response, 'LoggedIn Successfully');
		} else {
			return renderWithJson($result, 'Please choose one provider.', '', 1);
		}
	} catch(Exception $e) {
		return renderWithJson($result, $e->getMessage(), '', 1);
	}
});
/**
 * POST usersForgotPasswordPost
 * Summary: User forgot password
 * Notes: User forgot password
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/users/forgot_password', function ($request, $response, $args) {
    $result = array();
    $args = $request->getParsedBody();
    $user = Models\User::where('email', $args['email'])->first();
    if (!empty($user)) {
        $validationErrorFields = $user->validate($args);
        if (empty($validationErrorFields) && !empty($user)) {
            $password = uniqid();
            $user->password = getCryptHash($password);
            try {
                $user->save();
                $emailFindReplace = array(
                    '##USERNAME##' => $user['username'],
                    '##PASSWORD##' => $password,
                );
                sendMail('forgotpassword', $emailFindReplace, $user['email']);
                return renderWithJson($result, 'An email has been sent with your new password', '', 0);
            } catch (Exception $e) {
                return renderWithJson($result, 'Email Not found', '', 1);
            }
        } else {
            return renderWithJson($result, 'Process could not be found', $validationErrorFields, 1);
        }
    } else {
        return renderWithJson($result, 'No data found', '', 1);
    }
});
/**
 * PUT UsersuserIdChangePasswordPut .
 * Summary: update change password
 * Notes: update change password
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/users/change_password', function ($request, $response, $args) {
    global $authUser;
    $result = array();
    $args = $request->getParsedBody();
    $user = Models\User::find($authUser->id);
    $validationErrorFields = $user->validate($args);
    $password = crypt($args['password'], $user['password']);
    if (empty($validationErrorFields)) {
        if ($password == $user['password']) {
            $change_password = $args['new_password'];
            $user->password = getCryptHash($change_password);
            try {
                $user->save();
                $emailFindReplace = array(
                    '##PASSWORD##' => $args['new_password'],
                    '##USERNAME##' => $user['username']
                );
                if ($authUser['role_id'] == \Constants\ConstUserTypes::Admin) {
                    sendMail('adminchangepassword', $emailFindReplace, $user->email);
                } else {
                    sendMail('changepassword', $emailFindReplace, $user['email']);
                }
                $result['data'] = $user->toArray();
                return renderWithJson($result, 'Success','', 0);
            } catch (Exception $e) {
                return renderWithJson($result, 'User Password could not be updated. Please, try again', '', 1);
            }
        } else {
            return renderWithJson($result, 'Password is invalid . Please, try again', '', 1);
        }
    } else {
        return renderWithJson($result, 'User Password could not be updated. Please, try again', $validationErrorFields, 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
/**
 * POST AdminChangePasswordToUser .
 * Summary: update change password
 * Notes: update change password
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/users/change_password', function ($request, $response, $args) {
    global $authUser;
    $result = array();
    $args = $request->getParsedBody();
    $user = Models\User::find($args['user_id']);
    $validationErrorFields = $user->validate($args);
    $validationErrorFields['unique'] = array();
    if (!empty($args['new_password']) && !empty($args['new_confirm_password']) && $args['new_password'] != $args['new_confirm_password']) {
        array_push($validationErrorFields['unique'], 'Password and confirm password should be same');
    }
    if (empty($validationErrorFields['unique'])) {
        unset($validationErrorFields['unique']);
    }
    if (empty($validationErrorFields)) {
        $change_password = $args['new_password'];
        $user->password = getCryptHash($change_password);
        try {
            $user->save();
            $emailFindReplace = array(
                '##PASSWORD##' => $args['new_password'],
                '##USERNAME##' => $user['username']
            );
            sendMail('adminchangepassword', $emailFindReplace, $user->email);
            $result['data'] = $user->toArray();
            return renderWithJson($result, 'Success','', 0);
        } catch (Exception $e) {
            return renderWithJson($result, 'User Password could not be updated. Please, try again', '', 1);
        }
    } else {
        return renderWithJson($result, 'User Password could not be updated. Please, try again', $validationErrorFields, 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
/**
 * GET usersLogoutGet
 * Summary: User Logout
 * Notes: oauth
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/users/logout', function ($request, $response, $args) {
    if (!empty($_GET['token'])) {
        try {
            $oauth = Models\OauthAccessToken::where('access_token', $_GET['token'])->delete();
            $result = array(
                'status' => 'success',
            );
            return renderWithJson($result, 'Success','', 0);
        } catch (Exception $e) {
            return renderWithJson(array(), 'Please verify in your token', '', 1);
        }
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
/*app->GET('/api/v1/company_contestants', function ($request, $response, $args) {    
    $queryParams = $request->getQueryParams();
    global $authUser;
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $queryParams['role_id'] = \Constants\ConstUserTypes::Employer;		
		$enabledIncludes = array(
			'attachment'
		);
        $users = Models\User::with($enabledIncludes);
        $users = $users->Filter($queryParams)->paginate($count);
        if (!empty($authUser) && $authUser->role_id == '1') {
            $user_model = new Models\User;
            $users->makeVisible($user_model->hidden);
        }
        $users = $users->toArray();
        $data = $users['data'];
		unset($users['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $users
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));*/
$app->GET('/api/v1/contestant_user', function ($request, $response, $args) {    
    $queryParams = $request->getQueryParams();    
    global $authUser;
    $result = array();
    try {
        $users = Models\User::where('is_email_confirmed', true)->where('role_id', \Constants\ConstUserTypes::Employer)->get()->toArray();
		$result = array(
            'data' => $users
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson(array(), $message = 'No record found', $fields = '', $isError = 1);
    }
});
$app->GET('/api/v1/contestants', function ($request, $response, $args) {    
    $queryParams = $request->getQueryParams();    
    global $authUser;
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $queryParams['role_id'] = \Constants\ConstUserTypes::Employer;
		$queryParams['is_email_confirmed'] = true;
		$queryParams['is_active'] = true;
		if (isset($queryParams['category_id'])) {
			$enabledIncludes = array(
				'attachment',
				'category'
			);
		} else {
			$enabledIncludes = array(
				'attachment'
			);
		}
		if (!empty($queryParams['contest_id'])) {
			$enabledIncludes = array_merge($enabledIncludes,array('contest'));
        }
        $users = Models\User::with($enabledIncludes);
        $users = $users->Filter($queryParams)->paginate($count);
        if (!empty($authUser) && $authUser->role_id == '1') {
            $user_model = new Models\User;
            $users->makeVisible($user_model->hidden);
        }
        $users = $users->toArray();
        $data = $users['data'];
		unset($users['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $users
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
});
$app->GET('/api/v1/contestants/highest_votes', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    global $authUser;
    $result = array();
    try {
		$end_date = date('Y-m-d');
		$contests = Models\Contest::select('end_date')->where('type_id', 1)->whereDate('end_date', '>=', $end_date)->first();
		if (!empty($contests)) {
			$contests = $contests->toArray();
			$data = array();
			$enabledIncludes = array(
				'attachment'
			);
			$third_highest_votes = Models\User::select('votes')->where('is_active', true)->where('role_id', \Constants\ConstUserTypes::Employer)->where('votes','<>', 0)->orderBy('votes', 'DESC')->limit(1)->skip(2)->get()->toArray();
			$highest_votes = array();
			if (!empty($third_highest_votes)) {
				$enabledIncludes = array(
					'attachment',
					'category'
				);
				$highest_votes = Models\User::with($enabledIncludes)->where('is_email_confirmed', true)->where('is_active', true)->where('role_id', \Constants\ConstUserTypes::Employer)->where('votes','<>', 0)->where('votes','>=', $third_highest_votes[0]['votes'])->orderBy('votes', 'DESC')->get()->toArray();
			}
			$highest_votes_list = array();
			if ((!empty($highest_votes))) {
				$highest_votes_list['title'] = "Top Female Influencer of the Year";
				$highest_votes_list['data'] = (!empty($highest_votes)) ? $highest_votes : array();
			} else {
				$highest_votes_list = array();
			}	
			$sql = "select * from(
					SELECT user_categories.user_id,user_categories.category_id, user_categories.votes,users.first_name,categories.id,categories.name,categories.slug,
					rank() over(partition by user_categories.category_id order by user_categories.votes desc) as rank_vote
					FROM user_categories,users,categories
					where user_categories.user_id = users.id
					and user_categories.category_id = categories.id
					and user_categories.votes <> 0
					and users.role_id = ".\Constants\ConstUserTypes::Employer."
					and users.is_email_confirmed = 1
					and users.is_active = 1
					order by user_categories.category_id,users.first_name) user_data
					where rank_vote = 1";
			$category_highest_votes = Capsule::select($sql);
			if(!empty($category_highest_votes)) {
				$category_highest_votes = json_decode(json_encode($category_highest_votes), true);
				$user_ids = array_column($category_highest_votes, 'user_id');
				$category_highest_votes_users = Models\User::with($enabledIncludes)->whereIn('id', $user_ids)->get()->toArray();
				$users = array();
				foreach ($category_highest_votes as $category_highest_vote) {
					$user_id = $category_highest_vote['user_id'];
					$user_data = array_filter($category_highest_votes_users , function ($elem) use($user_id) {
																  return $elem['id'] == $user_id;
																});
					$category_data = array();
					$category_data = current($user_data);
					$category_data['category'] = array(
													'id' => $category_highest_vote['category_id'],
													'name' => $category_highest_vote['name'],
													'votes' => $category_highest_vote['votes'],
													'category' => array(
														'slug' => $category_highest_vote['slug']
													)
													);
					$users[] = $category_data;
				}
			}
			$data['highest_votes'] = $highest_votes_list;
			$data['category_highest_votes'] = (!empty($users)) ? $users : array();
			$data['left_time'] = strtotime($contests['end_date']);
			$result = array(
				'data' => $data
			);
		}	
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $e->getMessage(), $isError = 1);
    }
});
$app->GET('/api/v1/contestants/recent_winner', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
	global $_server_domain_url;
    $result = array();
    try {
		$data = array();
        $enabledIncludes = array(
            'attachment'
		);
		$sql = "select * from(
				SELECT user_categories.user_id,user_categories.category_id, user_categories.votes,users.first_name,categories.id,categories.name,
				rank() over(partition by user_categories.category_id order by user_categories.votes desc) as rank_vote
				FROM user_categories,users,categories
				where user_categories.user_id = users.id
				and user_categories.category_id = categories.id
				and user_categories.votes <> 0
				and users.role_id = ".\Constants\ConstUserTypes::Employer."
				and users.is_email_confirmed = 1
				and users.is_active = 1
				order by user_categories.category_id,users.first_name) user_data
				where rank_vote = 1";
		$category_highest_votes = Capsule::select($sql);
		if(!empty($category_highest_votes)) {
			$category_highest_votes = json_decode(json_encode($category_highest_votes), true);
			$user_ids = array_column($category_highest_votes, 'user_id');
			$category_highest_votes_users = Models\User::with($enabledIncludes)->whereIn('id', $user_ids)->get()->toArray();
			$users = array();
			foreach ($category_highest_votes as $category_highest_vote) {
				$user_id = $category_highest_vote['user_id'];
				$user_data = array_filter($category_highest_votes_users , function ($elem) use($user_id) {
															  return $elem['id'] == $user_id;
															});
				$category_data = array();
				$category_data = current($user_data);
				$category_data['category'] = array('id' => $category_highest_vote['category_id'],'name' => $category_highest_vote['name'],'votes' => $category_highest_vote['votes']);
				$socials = array();
				if ($category_data['instagram_url'] != '') {
					$socialSub = array();
					$socialSub['id'] = 1;
					$socialSub['name'] = 'Instagram';
					$socialSub['url'] = $category_data['instagram_url'];
					$socialSub['mobile_image'] = $_server_domain_url.'/images/static/instagram_mobile.png';
					$socialSub['web_image'] = $_server_domain_url.'/images/static/instagram.png';
					$socials[] = $socialSub;
				}
				if ($category_data['tiktok_url'] != '') {
					$socialSub = array();
					$socialSub['id'] = 2;
					$socialSub['name'] = 'Tiktok';
					$socialSub['url'] = $category_data['tiktok_url'];
					$socialSub['mobile_image'] = $_server_domain_url.'/images/static/tiktok_mobile.png';
					$socialSub['web_image'] = $_server_domain_url.'/images/static/tiktok.png';
					$socials[] = $socialSub;
				}
				if ($category_data['youtube_url'] != '') {
					$socialSub = array();
					$socialSub['id'] = 3;
					$socialSub['name'] = 'Youtube';
					$socialSub['url'] = $category_data['youtube_url'];
					$socialSub['mobile_image'] = $_server_domain_url.'/images/static/youtube_mobile.png';
					$socialSub['web_image'] = $_server_domain_url.'/images/static/youtube.png';
					$socials[] = $socialSub;
				}
				if ($category_data['twitter_url'] != '') {
					$socialSub = array();
					$socialSub['id'] = 4;
					$socialSub['name'] = 'Twitter';
					$socialSub['url'] = $category_data['twitter_url'];
					$socialSub['mobile_image'] = $_server_domain_url.'/images/static/twitter_mobile.png';
					$socialSub['web_image'] = $_server_domain_url.'/images/static/twitter.png';
					$socials[] = $socialSub;
				}
				if ($category_data['facebook_url'] != '') {
					$socialSub = array();
					$socialSub['id'] = 5;
					$socialSub['name'] = 'Facebook';
					$socialSub['url'] = $category_data['facebook_url'];
					$socialSub['mobile_image'] = $_server_domain_url.'/images/static/facebook_mobile.png';
					$socialSub['web_image'] = $_server_domain_url.'/images/static/facebook.png';
					$socials[] = $socialSub;
				}
				$category_data['socials'] = $socials;
				$users[] = $category_data;
			}
		}
		$result = array(
            'data' => (!empty($users)) ? $users : array()
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $e->getMessage(), $isError = 1);
    }
});
/**
 * POST UserPost
 * Summary: Create New user by admin
 * Notes: Create New user by admin
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/users', function ($request, $response, $args) {
	global $authUser;
	$args = $request->getParsedBody();
    $result = array();
    $user = new Models\User($args);
    $validationErrorFields = $user->validate($args);
    $validationErrorFields['unique'] = array();
    $validationErrorFields['required'] = array();
    if (checkAlreadyUsernameExists($args['username'])) {
        array_push($validationErrorFields['unique'], 'username');
    }
    if (checkAlreadyEmailExists($args['email'])) {
        array_push($validationErrorFields['unique'], 'email');
    }
    if (empty($validationErrorFields['unique'])) {
        unset($validationErrorFields['unique']);
    }
    if (empty($validationErrorFields['required'])) {
        unset($validationErrorFields['required']);
    }
    if (!empty($args['is_active'])) {
        $user->is_active = $args['is_active'];
     }
     if (!empty($args['is_email_confirmed'])) {
        $user->is_email_confirmed = $args['is_email_confirmed'];
     } 
    if (empty($validationErrorFields)) {
        $user->password = getCryptHash($args['password']);
        $user->role_id = $args['role_id'];  
        try {
            unset($user->image);
            unset($user->cover_photo);       
            $user->save();
            if (!empty($args['image'])) {
                saveImage('UserAvatar', $args['image'], $user->id);
            }
            if (!empty($args['cover_photo'])) {
                saveImage('CoverPhoto', $args['cover_photo'], $user->id);
            }
            $emailFindReplace_user = array(
                '##USERNAME##' => $user->username,
                '##LOGINLABEL##' => (USER_USING_TO_LOGIN == 'username') ? 'Username' : 'Email',
                '##USEDTOLOGIN##' => (USER_USING_TO_LOGIN == 'username') ? $user->username : $user->email,
                '##PASSWORD##' => $args['password']
            );
            sendMail('adminuseradd', $emailFindReplace_user, $user->email);
            $enabledIncludes = array(
                'attachment',
                'cover_photo'
            );
            $result = Models\User::with($enabledIncludes)->find($user->id)->toArray();
            return renderWithJson($result, 'Success','', 0);
        } catch (Exception $e) {
            return renderWithJson($result, 'User could not be added. Please, try again.', '', 1);
        }
    } else {
        return renderWithJson($result, 'User could not be added. Please, try again.', $validationErrorFields, 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->GET('/api/v1/companies', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	$queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
		$queryParams['role'] = 'company';
		$enabledIncludes = array(
			'address'
		);
        $respones = Models\User::with($enabledIncludes)->Filter($queryParams)->paginate($count);
		$user_model = new Models\User;
		$respones->makeVisible($user_model->hidden);
		$respones = $respones->toArray();
        $data = $respones['data'];
        unset($respones['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $respones
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin'));
/**
 * GET UseruserIdGet
 * Summary: Get particular user details
 * Notes: Get particular user details
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/users/{userId}', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	try {
		$queryParams = $request->getQueryParams();
		$result = array();
		$enabledIncludes = array(
					'category',
					'attachments'
				);
		$enabledUserIncludes = array(
			'attachment',
			'address',
			'vote_category'
		);
		$user = Models\User::with($enabledUserIncludes)->where('id', $request->getAttribute('userId'))->orWhere('username', $request->getAttribute('userId'))->first();
		$_GET['user_id'] = $user->id;
		$authUserId = null;
		$usercategories = Models\UserCategory::select('id', 'category_id')->where('is_active', true)->where('user_id', $user->id)->get()->toArray();
		$catIds = array_column($usercategories, 'category_id');
		$categories = Models\Category::where('is_active', true)->whereIn('id', $catIds)->orderBy('name', 'asc')->get()->toArray();
		
		if (!empty($authUser['id'])) {
			$authUserId = $authUser['id'];
			$current_user = '';
			$subscription_end_date = '';
			$subscription_end_date  = $user->subscription_end_date;
			if ($user->id != $authUserId) {
				$current_user = Models\User::with($enabledUserIncludes)->where('id', $authUserId)->first();
				$user_model = new Models\User;
				$current_user->makeVisible($user_model->hidden);
				$user->subscription_end_date = $current_user->subscription_end_date;
				$subscription_end_date  = $current_user->subscription_end_date;
			} else {
				$user_model = new Models\User;
				$user->makeVisible($user_model->hidden);
				$count = Models\Attachment::where('user_id', $authUser->id)->where('is_admin_approval', 0)->count();
				$user->is_admin_approval = ($count > 0) ? true : false;
				if ($user->address == null) {
					$user->address->id = 0;
					$user->address->user_id = 0;
					$user->address->name = '';
					$user->address->addressline1 = '';
					$user->address->addressline2 = '';
					$user->address->city = '';
					$user->address->state = '';
					$user->address->country = '';
					$user->address->zipcode = '';
					$user->address->is_default = 1;
				}
			}
			$categories = array();
			if ($authUserId == $user->id && $authUser['role_id'] == \Constants\ConstUserTypes::Employer) {
				$user->is_subscribed = true;
				$usercategories = Models\UserCategory::select('id', 'category_id')->where('is_active', true)->where('user_id', $user->id)->get()->toArray();
				$catIds = array_column($usercategories, 'category_id');
				$categories = Models\Category::where('is_active', true)->whereIn('id', $catIds)->orderBy('name', 'asc')->get()->toArray();
				if (!empty($usercategories)) {
					$category_ids = array_column($usercategories, 'id');
					$categoryIdArr = Models\Attachment::select('id', 'foreign_id')->whereIn('foreign_id', $category_ids)->where('user_id', $user->id)->where('class', 'UserProfile')->where('is_admin_approval', '<>' , 3)->get()->toArray();
				}
				if (!empty($categoryIdArr)) {
					$category_ids = array_column($categoryIdArr, 'foreign_id');
					$user->subscribed_data = (!empty($category_ids)) ? Models\UserCategory::with($enabledIncludes)->where('is_active', true)->whereIn('id', $category_ids)->where('user_id', $user->id)->get() : array();
				} else {
					$user->subscribed_data = array();
				}
			} else {
				$user->is_subscribed = ($subscription_end_date && strtotime($subscription_end_date) >= strtotime(date('Y-m-d'))) ? true : false;
				if ($user->is_subscribed) {
					if (isset($queryParams['category_id']) && $queryParams['category_id'] != "") {
						$usercategoriesList = Models\UserCategory::select('id')->where('is_active', true)->where('category_id', $queryParams['category_id'])->get()->toArray();
						if (!empty($usercategoriesList)) {
							$category_ids = array_column($usercategoriesList, 'id');
							$categoryIdArr = Models\Attachment::select('id', 'foreign_id')->whereIn('foreign_id', $category_ids)->where('user_id', $user->id)->where('class', 'UserProfile')->where('is_admin_approval', 2)->get()->toArray();
						}
					} else {
						if (!empty($usercategories)) {
							$category_ids = array_column($usercategories, 'id');						
							$categoryIdArr = Models\Attachment::select('id', 'foreign_id')->whereIn('foreign_id', $category_ids)->where('user_id', $user->id)->where('class', 'UserProfile')->where('is_admin_approval', 2)->get()->toArray();
						}
					}
				}
			}			
		}
		if (!isset($user->is_subscribed) || $user->is_subscribed == false) {
			if (isset($queryParams['category_id']) && $queryParams['category_id'] != "") {
				$usercategoriesList = Models\UserCategory::select('id')->where('is_active', true)->where('category_id', $queryParams['category_id'])->get()->toArray();
				if (!empty($usercategoriesList)) {
					$category_ids = array_column($usercategoriesList, 'id');
					$categoryIdArr = Models\Attachment::select('id', 'foreign_id')->whereIn('foreign_id', $category_ids)->where('user_id', $user->id)->where('class', 'UserProfile')->where('is_admin_approval', 2)->where('ispaid', 0)->get()->toArray();
				}
			} else {
				if (!empty($usercategories)) {
					$category_ids = array_column($usercategories, 'id');						
					$categoryIdArr = Models\Attachment::select('id', 'foreign_id')->whereIn('foreign_id', $category_ids)->where('user_id', $user->id)->where('class', 'UserProfile')->where('is_admin_approval', 2)->where('ispaid', 0)->get()->toArray();
				}
			}
			$enabledIncludes = array(
				'category',
				'attachments_free'
			);
		}
		
		if (!empty($categoryIdArr)) {
			$category_ids = array_column($categoryIdArr, 'foreign_id');
			$user->subscribed_data = (!empty($category_ids)) ? json_decode(str_replace('attachments_free', 'attachments' , json_encode(Models\UserCategory::with($enabledIncludes)->where('is_active', true)->whereIn('id', $category_ids)->where('user_id', $user->id)->get())), true) : array();
		} else {
			$user->subscribed_data = array();
		}
			
		if (!empty($user)) {
			$user = $user->toArray();
			$user['categories'] = (!isset($queryParams['category_id']) || (isset($queryParams['category_id']) && $queryParams['category_id'] == '') && !empty($categories)) ? $categories : array();
			$socials = array();
			if ($user['instagram_url'] != '') {
				$socialSub = array();
				$socialSub['id'] = 1;
				$socialSub['name'] = 'Instagram';
				$socialSub['url'] = $user['instagram_url'];
				$socialSub['mobile_image'] = $_server_domain_url.'/images/static/instagram_mobile.png';
				$socialSub['web_image'] = $_server_domain_url.'/images/static/instagram.png';
				$socials[] = $socialSub;
			}
			if ($user['tiktok_url'] != '') {
				$socialSub = array();
				$socialSub['id'] = 2;
				$socialSub['name'] = 'Tiktok';
				$socialSub['url'] = $user['tiktok_url'];
				$socialSub['mobile_image'] = $_server_domain_url.'/images/static/tiktok_mobile.png';
				$socialSub['web_image'] = $_server_domain_url.'/images/static/tiktok.png';
				$socials[] = $socialSub;
			}
			if ($user['youtube_url'] != '') {
				$socialSub = array();
				$socialSub['id'] = 3;
				$socialSub['name'] = 'Youtube';
				$socialSub['url'] = $user['youtube_url'];
				$socialSub['mobile_image'] = $_server_domain_url.'/images/static/youtube_mobile.png';
				$socialSub['web_image'] = $_server_domain_url.'/images/static/youtube.png';
				$socials[] = $socialSub;
			}
			if ($user['twitter_url'] != '') {
				$socialSub = array();
				$socialSub['id'] = 4;
				$socialSub['name'] = 'Twitter';
				$socialSub['url'] = $user['twitter_url'];
				$socialSub['mobile_image'] = $_server_domain_url.'/images/static/twitter_mobile.png';
				$socialSub['web_image'] = $_server_domain_url.'/images/static/twitter.png';
				$socials[] = $socialSub;
			}
			if ($user['facebook_url'] != '') {
				$socialSub = array();
				$socialSub['id'] = 5;
				$socialSub['name'] = 'Facebook';
				$socialSub['url'] = $user['facebook_url'];
				$socialSub['mobile_image'] = $_server_domain_url.'/images/static/facebook_mobile.png';
				$socialSub['web_image'] = $_server_domain_url.'/images/static/facebook.png';
				$socials[] = $socialSub;
			}
			$user['socials'] = $socials;
			if ($user['address'] == '') {
				$user['address']['id'] = 0;
				$user['address']['user_id'] = 0;
				$user['address']['name'] = '';
				$user['address']['addressline1'] = '';
				$user['address']['addressline2'] = '';
				$user['address']['city'] = '';
				$user['address']['state'] = '';
				$user['address']['country'] = '';
				$user['address']['zipcode'] = '';
				$user['address']['is_default'] = 1;
			}
			$result['data'] = $user;
			if (!empty($_GET['type']) && $_GET['type'] == 'view' && (empty($authUser) || (!empty($authUser) && $authUser['id'] != $request->getAttribute('userId')))) {
				insertViews($request->getAttribute('userId'), 'User');
			}
			return renderWithJson($result, 'Success','', 0);
		} else {
			return renderWithJson($result, 'No record found', '', 1, 404);
		}
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
});
/**
 * GET AuthUserID
 * Summary: Get particular user details
 * Notes: Get particular user details
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/me', function ($request, $response, $args) {
    global $authUser;
    $result = array();
    $enabledIncludes = array(
        'attachment',
        'role'
    );
    $user = Models\User::with($enabledIncludes)->where('id', $authUser->id)->first();
    $user_model = new Models\User;
    $user->makeVisible($user_model->hidden);
    if (!empty($user)) {
        $result['data'] = $user;
        return renderWithJson($result, 'Success','', 0);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
/**
 * PUT UsersuserIdPut
 * Summary: Update user
 * Notes: Update user
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/users', function ($request, $response, $args) {
    global $authUser;
    $args = $request->getParsedBody();	
    $result = array();
    $user = Models\User::find($authUser->id);
    $validation = true;
    if (!empty($user)) {
		if ($authUser['role_id'] != \Constants\ConstUserTypes::Admin) {
			unset($args['username']);
			unset($args['is_paypal_connect']);
			unset($args['is_stripe_connect']);
			unset($args['subscription_end_date']);
			unset($args['votes']);
			unset($args['rank']);
		}
        if ($validation) {
            $address = $args['address'];
			if (isset($args['image']) && $args['image'] != '') {
				$image = $args['image'];
				saveImage('UserAvatar', $image, $user->id);
				unset($args['image']);
			}
			if (isset($args['cover_photo']) && $args['cover_photo'] != '') {
				saveImage('CoverPhoto', $args['cover_photo'], $user->id);
				unset($args['cover_photo']);
			}
			if (isset($args['address']) && $args['address'] != '') {
				$userAdd = Models\UserAddress::where('user_id', $authUser->id)->where('is_active', 1)->first();
				if ($userAdd && !empty($userAdd)) {
					Models\UserAddress::where('user_id', $authUser->id)->where('is_default', true)->update($args['address']);
				} else {
					$address = new Models\UserAddress;
					$address->addressline1 = $args['address']['addressline1'];
					$address->addressline2 = $args['address']['addressline2'];
					$address->city = $args['address']['city'];
					$address->state = $args['address']['state'];
					$address->country = $args['address']['country'];
					$address->zipcode = $args['address']['zipcode'];
					$address->user_id = $user->id;
					$address->is_default = true;
					$address->name = 'Default';
					$address->save();
				}
				unset($args['address']);
			}
			$user->fill($args);
            try {
                $user->save();                
                $enabledIncludes = array(
                    'attachment',
					'address'
                );
                $user = Models\User::with($enabledIncludes)->find($user->id);
                $result['data'] = $user->toArray();
                return renderWithJson($result, 'Profile updated Successfully','', 0);
            } catch (Exception $e) {
                return renderWithJson($result, 'User could not be updated. Please, try again.', '', 1);
            }
        } else {
            return renderWithJson($result, 'Country is required', '', 1);
        }
    } else {
        return renderWithJson($result, 'Invalid user Details, try again.', '', 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->PUT('/api/v1/user_image', function ($request, $response, $args) {
    global $authUser;
    $args = $request->getParsedBody();	
    $result = array();
    if (isset($args['image']) && $args['image'] != '') {
		$image = $args['image'];
		saveImage('UserAvatar', $args['image'], $authUser->id);
		return renderWithJson(array(), 'Profile image updated Successfully','', 0);
	} else {
		return renderWithJson(array(), 'Profile image could not be updated. Please, try again.', '', 1);
	}
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
/**
 * DELETE UseruserId Delete
 * Summary: DELETE user by admin
 * Notes: DELETE user by admin
 * Output-Formats: [application/json]
 */
$app->DELETE('/api/v1/users/{userId}', function ($request, $response, $args) {
    $result = array();
    $user = Models\User::find($request->getAttribute('userId'));
    $data = $user;
    if (!empty($user)) {
        try {
            $user->delete();
            $emailFindReplace = array(
                '##USERNAME##' => $data['username']
            );
            sendMail('adminuserdelete', $emailFindReplace, $data['email']);
            $result = array(
                'status' => 'success',
            );
            Models\UserLogin::where('user_id', $request->getAttribute('userId'))->delete();
            return renderWithJson($result, 'Success','', 0);
        } catch (Exception $e) {
            return renderWithJson($result, 'User could not be deleted. Please, try again.', '', 1);
        }
    } else {
        return renderWithJson($result, 'Invalid User details.', '', 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
/**
 * GET ProvidersGet
 * Summary: all providers lists
 * Notes: all providers lists
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/providers', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $providers = Models\Provider::Filter($queryParams)->paginate($count)->toArray();
        $data = $providers['data'];
        unset($providers['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $providers
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
});
/**
 * GET  ProvidersProviderIdGet
 * Summary: Get  particular provider details
 * Notes: GEt particular provider details.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/providers/{providerId}', function ($request, $response, $args) {
    $result = array();
    $provider = Models\Provider::find($request->getAttribute('providerId'));
    if (!empty($provider)) {
        $result['data'] = $provider->toArray();
        return renderWithJson($result, 'Success','', 0);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
});
/**
 * PUT ProvidersProviderIdPut
 * Summary: Update provider details
 * Notes: Update provider details.
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/providers/{providerId}', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $result = array();
    $provider = Models\Provider::find($request->getAttribute('providerId'));
    $validationErrorFields = $provider->validate($args);
    if (empty($validationErrorFields)) {
        $provider->fill($args);
        try {
            $provider->save();
            $result['data'] = $provider->toArray();
            return renderWithJson($result, 'Success','', 0);
        } catch (Exception $e) {
            return renderWithJson($result, 'Provider could not be updated. Please, try again', '', 1);
        }
    } else {
        return renderWithJson($result, 'Provider could not be updated. Please, try again', $validationErrorFields, 1);
    }
});
/**
 * POST orderPost
 * Summary: Creates a new page
 * Notes: Creates a new page
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/order', function ($request, $response, $args) {
    global $authUser, $_server_domain_url;
    $args = $request->getParsedBody();
    $result = array();
    if (!empty($args['class']) && !empty($args['foreign_id'])) {
        $args['user_id'] = isset($args['user_id']) ? $args['user_id'] : $authUser->id;
        $result = Models\Contest::processOrder($args);
        if (!empty($result)) {
            return renderWithJson($result, 'Success','', 0);
        } else {
            return renderWithJson($result, $message = 'Order could not added. No record found', '', $isError = 1);
        }
    } else {
        $validationErrorFields['class'] = 'class required';
        $validationErrorFields['foreign_id'] = 'foreign_id required';
        return renderWithJson($result, $message = 'Order could not added', $validationErrorFields, $isError = 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
/**
 * GET RoleGet
 * Summary: Get roles lists
 * Notes: Get roles lists
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/roles', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $roles = Models\Role::Filter($queryParams)->paginate($count)->toArray();
        $data = $roles['data'];
        unset($roles['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $roles
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin'));
/**
 * GET RolesIdGet
 * Summary: Get paticular email templates
 * Notes: Get paticular email templates
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/roles/{roleId}', function ($request, $response, $args) {
    $result = array();
    $role = Models\Role::find($request->getAttribute('roleId'));
    if (!empty($role)) {
        $result = $role->toArray();
        return renderWithJson($result, 'Success','', 0);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
})->add(new ACL('canAdmin'));
/**
 * GET UsersUserIdTransactionsGet
 * Summary: Get user transactions list.
 * Notes: Get user transactions list.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/users/{userId}/transactions', function ($request, $response, $args) {
    global $authUser;
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $enabledIncludes = array(
            'user',
            'other_user',
            'foreign_transaction',
            'payment_gateway'
        );
        $transactions = Models\Transaction::with($enabledIncludes);
        if (!empty($authUser['id'])) {
            $user_id = $authUser['id'];
            $transactions->where(function ($q) use ($user_id) {
                $q->where('user_id', $user_id)->orWhere('to_user_id', $user_id);
            });
        }
        $transactions = $transactions->Filter($queryParams)->paginate($count);
        $data = $transactions->toArray();
        $result = array(
            'data' => $data,
            '_metadata' => $transactionsNew
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
/**
 * GET paymentGatewayGet
 * Summary: Filter  payment gateway
 * Notes: Filter payment gateway.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/payment_gateways', function ($request, $response, $args) {
	global $authUser;
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $paymentGateways = Models\PaymentGateway::with('attachment')->where('is_active', true)->Filter($queryParams)->get()->toArray();
		$payGateway = array();
		$addCard = array();
		if (!empty($paymentGateways)) {
			foreach($paymentGateways as $paymentGateway) {
				//if ($paymentGateway['name'] != 'Add Card') {
				//	$payGateway[] = $paymentGateway;
				//} else {
					$addCard[] = $paymentGateway;
				// }
			}
		}
        // $cards = Models\Card::select('id', 'card_display_number', 'expiry_date', 'name')->where('user_id', $authUser->id)->get()->toArray();
        $result = array(
            'data' => $addCard // array_merge(array_merge($payGateway, $cards), $addCard)
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->PUT('/api/v1/payment_gateway/{id}', function ($request, $response, $args) {
    global $authUser;
	$args = $request->getParsedBody();
	$paymentGateway = Models\PaymentGateway::find($request->getAttribute('id'));
	$result = array();
	try {
		if (!empty($args['image']) && $paymentGateway->id) {
			saveImage('PaymentGateway', $args['image'], $paymentGateway->id);
		}
		$result = $paymentGateway->toArray();
		return renderWithJson($result, 'Success','', 0);		
	} catch (Exception $e) {
		return renderWithJson($result, 'PaymentGateway could not be updated. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin'));
/**
 * POST pagePost
 * Summary: Create New page
 * Notes: Create page.
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/pages', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $result = array();
    $page = new Models\Page($args);
    $validationErrorFields = $page->validate($args);
    if (empty($validationErrorFields)) {
        $page->slug = getSlug($page->title);
        try {
            $page->save();
            $result = $page->toArray();
            return renderWithJson($result, 'Success','', 0);
        } catch (Exception $e) {
            return renderWithJson($result, 'Page user could not be added. Please, try again.', '', 1);
        }
    } else {
        return renderWithJson($result, 'Page could not be added. Please, try again.', $validationErrorFields, 1);
    }
})->add(new ACL('canAdmin'));
/**
 * GET PagePageIdGet.
 * Summary: Get page.
 * Notes: Get page.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/pages/{slug}', function ($request, $response, $args) {
    $result = array();
    $queryParams = $request->getQueryParams();
    try {
        $page = Models\Page::where('id', $request->getAttribute('slug'))->first();
        if (!empty($page)) {
            $result['data'] = $page->toArray();
            return renderWithJson($result, 'Success','', 0);
        } else {
            return renderWithJson($result, 'No record found.', '', 1, 404);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'No record found.', '', 1, 404);
    }
});
$app->GET('/api/v1/pages', function ($request, $response, $args) {
    $pages = Models\Page::select('title', 'url')->get()->toArray();
	$results = array(
		'data' => $pages
	);
	return renderWithJson($results, 'pages details list fetched successfully','', 0);
});
/**
 * PUT PagepageIdPut
 * Summary: Update page by admin
 * Notes: Update page by admin
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/pages/{pageId}', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $result = array();
    $page = Models\Page::find($request->getAttribute('pageId'));
    $validationErrorFields = $page->validate($args);
    if (empty($validationErrorFields)) {
        $oldPageTitle = $page->title;
        $page->fill($args);
        if ($page->title != $oldPageTitle) {
            $page->slug = $page->slug = getSlug($page->title);
        }
        try {
            $page->save();
            $result['data'] = $page->toArray();
            return renderWithJson($result, 'Success','', 0);
        } catch (Exception $e) {
            return renderWithJson($result, 'Page could not be updated. Please, try again.', '', 1);
        }
    } else {
        return renderWithJson($result, 'Page could not be updated. Please, try again.', $validationErrorFields, 1);
    }
})->add(new ACL('canAdmin'));
/**
 * DELETE PagepageIdDelete
 * Summary: DELETE page by admin
 * Notes: DELETE page by admin
 * Output-Formats: [application/json]
 */
$app->DELETE('/api/v1/pages/{pageId}', function ($request, $response, $args) {
    $result = array();
    $page = Models\Page::find($request->getAttribute('pageId'));
    try {
        if (!empty($page)) {
            $page->delete();
            $result = array(
                'status' => 'success',
            );
            return renderWithJson($result, 'Success','', 0);
        } else {
            return renderWithJson($result, 'No record found', '', 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Page could not be deleted. Please, try again.', '', 1);
    }
})->add(new ACL('canAdmin'));

/**
 * GET SettingcategoriesSettingCategoryIdGet
 * Summary: Get setting categories.
 * Notes: GEt setting categories.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/setting_categories/{settingCategoryId}', function ($request, $response, $args) {
    $result = array();
    $settingCategory = Models\SettingCategory::find($request->getAttribute('settingCategoryId'));
    if (!empty($settingCategory)) {
        $result['data'] = $settingCategory->toArray();
        return renderWithJson($result, 'Success','', 0);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
})->add(new ACL('canAdmin'));
/**
 * GET SettingGet .
 * Summary: Get settings.
 * Notes: GEt settings.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/settings', function ($request, $response, $args) {
	global $authUser;
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['is_mobile'])) {
            $settings = Models\Setting::select('name', 'value')->where('is_mobile', true)->get()->toArray();
        } else if (!empty($queryParams['is_web'])) {
			$settings = Models\Setting::select('name', 'value')->where('is_web', true)->get()->toArray();
		}
		$data = array();
		foreach($settings as $setting) {
			$data[$setting['name']] = $setting['value'];
		}
		$subscription = Models\Subscription::where('is_active', true)->get()->toArray();
		$data['SUBSCRIBE_NAME'] = $subscription[0]['description'];
		$data['SUBSCRIBE_PRICE'] = $subscription[0]['price'];
		$data['SUBSCRIBE_DAYS'] = $subscription[0]['days'];
		// if (!empty($authUser) && ($authUser->role_id == \Constants\ConstUserTypes::Admin || $authUser->role_id == \Constants\ConstUserTypes::Company)) {
		if (!empty($queryParams['is_web'])) {
			$file = __DIR__ . '/admin-config.php';
			$resultSet = array();
			if (file_exists($file)) {
				require_once $file;
				$data['MENU'] = $menus;
			}
		}
		$result = array(
			'data' => $data
		);
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
});
/**
 * GET settingssettingIdGet
 * Summary: GET particular Setting.
 * Notes: Get setting.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/settings/{settingId}', function ($request, $response, $args) {
    $result = array();
    $enabledIncludes = array(
        'setting_category'
    );
    $setting = Models\Setting::with($enabledIncludes)->find($request->getAttribute('settingId'));
    if (!empty($setting)) {
        $result['data'] = $setting->toArray();
        return renderWithJson($result, 'Success','', 0);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
})->add(new ACL('canAdmin'));
/**
 * PUT SettingsSettingIdPut
 * Summary: Update setting by admin
 * Notes: Update setting by admin
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/settings/{settingId}', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $result = array();
    $setting = Models\Setting::find($request->getAttribute('settingId'));
    $setting->fill($args);
    try {
        if (!empty($setting)) {
            if ($setting->name == 'ALLOWED_SERVICE_LOCATIONS') {
                $country_list = array();
                $city_list = array();
                $allowed_locations = array();
                if (!empty(!empty($args['allowed_countries']))) {
                    foreach ($args['allowed_countries'] as $key => $country) {
                        $country_list[$key]['id'] = $country['id'];
                        $country_list[$key]['name'] = $country['name'];
                        $country_list[$key]['iso_alpha2'] = '';
                        $country_details = Models\Country::select('iso_alpha2')->where('id', $country['id'])->first();
                        if (!empty($country_details)) {
                            $country_list[$key]['iso_alpha2'] = $country_details->iso_alpha2;
                        }
                    }
                    $allowed_locations['allowed_countries'] = $country_list;
                }
                if (!empty(!empty($args['allowed_cities']))) {
                    foreach ($args['allowed_cities'] as $key => $city) {
                        $city_list[$key]['id'] = $city['id'];
                        $city_list[$key]['name'] = $city['name'];
                    }
                    $allowed_locations['allowed_cities'] = $city_list;
                }
                $setting->value = json_encode($allowed_locations);
            }
            $setting->save();
            // Handle watermark image uploaad in settings
            if ($setting->name == 'WATERMARK_IMAGE' && !empty($args['image'])) {
                saveImage('WaterMark', $args['image'], $setting->id);
            }
            $result['data'] = $setting->toArray();
            return renderWithJson($result, 'Success','', 0);
        } else {
            return renderWithJson($result, 'No record found.', '', 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Setting could not be updated. Please, try again.', '', 1);
    }
})->add(new ACL('canAdmin'));
/**
 * GET EmailTemplateemailTemplateIdGet
 * Summary: Get paticular email templates
 * Notes: Get paticular email templates
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/email_templates/{emailTemplateId}', function ($request, $response, $args) {
    $result = array();
    $emailTemplate = Models\EmailTemplate::find($request->getAttribute('emailTemplateId'));
    if (!empty($emailTemplate)) {
        $result['data'] = $emailTemplate->toArray();
        return renderWithJson($result, 'Success','', 0);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
})->add(new ACL('canAdmin'));
/**
 * PUT EmailTemplateemailTemplateIdPut
 * Summary: Put paticular email templates
 * Notes: Put paticular email templates
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/email_templates/{emailTemplateId}', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $result = array();
    $emailTemplate = Models\EmailTemplate::find($request->getAttribute('emailTemplateId'));
    $validationErrorFields = $emailTemplate->validate($args);
    if (empty($validationErrorFields)) {
        $emailTemplate->fill($args);
        try {
            $emailTemplate->save();
            $result['data'] = $emailTemplate->toArray();
            return renderWithJson($result, 'Success','', 0);
        } catch (Exception $e) {
            return renderWithJson($result, 'Email template could not be updated. Please, try again', '', 1);
        }
    } else {
        return renderWithJson($result, 'Email template could not be updated. Please, try again', $validationErrorFields, 1);
    }
})->add(new ACL('canAdmin'));
$app->GET('/api/v1/attachments_profile', function ($request, $response, $args) {
	global $authUser;
	$userFiles = Models\Attachment::where('foreign_id', $authUser->id)->where('class', 'UserProfile')->get()->toArray();
	$response = array(
		'data' => $userFiles,
		'error' => array(
			'code' => 0,
			'message' => ''
		)
	);
	return renderWithJson($response);
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->POST('/api/v1/attachments', function ($request, $response, $args) {
    global $configuration;
	global $authUser;
    $args = $request->getQueryParams();
	$file = $request->getUploadedFiles();
	$class = $args['class'];
	$ispaid = isset($args['ispaid']) ? $args['ispaid']: null;
	if ($class == "UserProfile" || $class == "Product") {
		if (isset($args['url']) && $args['url'] != '') {
			$attachment = new Models\Attachment;
			$width = $info[0];
			$height = $info[1];
			$attachment->filename = $args['url'];
			$attachment->width = $width;
			$attachment->height = $height;
			$attachment->dir = '';
			$attachment->location = $args['location'];
			$attachment->caption = $args['caption'];
			$attachment->foreign_id = $user_category->id;
			$attachment->class = $class;
			$attachment->user_id = $authUser->id;
			$attachment->ispaid = $ispaid;
			$attachment->mimetype = $info['mime'];
			if (videoType($args['url']) == 'youtube') {
				$video_id = explode("?v=", $args['url']);
				$video_id = $video_id[1];
				$attachment->thumb = 'https://img.youtube.com/vi/'. $video_id.'/0.jpg';
			}
			$attachment->save();
			$response = array(
				'error' => array(
					'code' => 0,
					'message' => 'Successfully uploaded'
				)
			);
			return renderWithJson($response);
		}
		$fileArray = $_FILES['file'];
		$imageFileArray = $_FILES['image'];
		$isError = false;
		$user_category = null; 
		if ($class == "UserProfile") {
			$user_category = Models\UserCategory::where('user_id', $authUser->id)->where('category_id', $args['category_id'])->first();
		}
		$attachmentArray = array();
		if(!empty($file['file'])) {
			$i = 0;
			foreach($file['file'] as $newfile) {
				$type = pathinfo($newfile->getClientFilename(), PATHINFO_EXTENSION);
				$fileName = str_replace(' ', '_', str_replace('.'.$type,"",$newfile->getClientFilename()).'_'.time().'.'.$type);
				$attachmentArray[] = $fileName;
				$attachment_settings = getAttachmentSettings($class);
				$file_formats = explode(",", $attachment_settings['allowed_file_formats']);
				$file_formats = array_map('trim', $file_formats);
				$kilobyte = 1024;
				$megabyte = $kilobyte * 1024;
				$fileArray["type"][$i] = get_mime($fileArray['tmp_name'][$i]);				
				$current_file_size = round($fileArray["size"][$i] / $megabyte, 2);
				//if (in_array($fileArray["type"][$i], $file_formats) || empty($attachment_settings['allowed_file_formats'])) {
					if ($class == "UserProfile" && preg_match('/video\/*/',$fileArray["type"][$i])) {
						$filePath = APP_PATH.DIRECTORY_SEPARATOR.'media'.DIRECTORY_SEPARATOR.'UserProfile'.DIRECTORY_SEPARATOR.$user_category->id.DIRECTORY_SEPARATOR;
						if (!file_exists($filePath)) {
							mkdir($filePath,0777,true);
						}
						if (move_uploaded_file($newfile->file, $filePath.$fileName) === true) {
							$info = getimagesize($filePath.$fileName);
							$width = $info[0];
							$height = $info[1];
							$attachment = new Models\Attachment;
							$attachment->filename = $fileName;
							$attachment->width = $width;
							$attachment->height = $height;
							$attachment->location = $args['location'];
							$attachment->caption = $args['caption'];
							$attachment->dir = $class .DIRECTORY_SEPARATOR . $user_category->id;
							$attachment->foreign_id = $user_category->id;
							$attachment->class = $class;
							$attachment->ispaid = $ispaid;
							$attachment->mimetype = $info['mime'];
							$attachment->user_id = $authUser->id;
							$attachment->save();
							$attAttImageId = $attachment->id;
							$j = 0;
							foreach($file['image'] as $imageNewfile) {
								$imagetype = pathinfo($imageNewfile->getClientFilename(), PATHINFO_EXTENSION);
								$imageFileName = str_replace(' ', '_', str_replace('.'.$imagetype, '',$imageNewfile->getClientFilename()).'_'.time().'.'.$imagetype);
								$imageFileArray["type"][$j] = get_mime($imageFileArray['tmp_name'][$j]);				
								$current_file_size = round($imageFileArray["size"][$j] / $megabyte, 2);
								$imageClass = 'UserProfileVideoImage';
								$imageFilePath = APP_PATH.DIRECTORY_SEPARATOR.'media'.DIRECTORY_SEPARATOR.$imageClass.DIRECTORY_SEPARATOR.$user_category->id.DIRECTORY_SEPARATOR;
								if (!file_exists($imageFilePath)) {
									mkdir($imageFilePath,0777,true);
								}
								if (move_uploaded_file($imageNewfile->file, $imageFilePath.$imageFileName) === true) {
									$attachment = new Models\Attachment;
									$imageInfo = getimagesize($imageFilePath.$imageFileName);
									$width = $imageInfo[0];
									$height = $imageInfo[1];
									$attachment->filename = $imageFileName;
									$attachmentArray[] = $imageFileName;
									$attachment->width = $width;
									$attachment->height = $height;
									$attachment->location = $args['location'];
									$attachment->caption = $args['caption'];
									$attachment->ispaid = $ispaid;
									$attachment->dir = $imageClass .DIRECTORY_SEPARATOR . $user_category->id;
									$attachment->foreign_id = $attAttImageId;
									$attachment->class = $imageClass;
									$attachment->mimetype = $imageInfo['mime'];
									$attachment->user_id = $authUser->id;
									$attachment->save();
								} else {
									$isError = true;
								}
								$j++;
							}
						} else {
							$isError = true;
						}
					} else {
						if (!file_exists(APP_PATH . '/media/tmp/')) {
							mkdir(APP_PATH . '/media/tmp/',0777,true);
						}
						if ($type == 'php') {
							$type = 'txt';
						}
						if (move_uploaded_file($newfile->file, APP_PATH . '/media/tmp/' . $fileName) === true) {
							if ($class == "UserProfile") {
								$category_id = isset($args['category_id']) ? $args['category_id']: null;
								saveImage('UserProfile', $fileName, $user_category->id, true, $authUser->id, $ispaid, $args);
							}
						} else {
							$isError = true;
						}
					}
				//}
				$i++;
			}
		}
		if ($isError != true) {		
			$response = array(
								'attachments' => $attachmentArray,
								'error' => array(
									'code' => 0,
									'message' => 'Successfully uploaded'
								)
							);
		} else {
			$response = array(
									'error' => array(
										'code' => 1,
										'message' => 'Attachment could not be added.',
										'fields' => ''
									)
								);
		}
		return renderWithJson($response);
	} else {
		$class = $args['class'];
		$user_category = null; 
		if(!empty($file)) {
			$newfile = $file['file'];
			$type = pathinfo($newfile->getClientFilename(), PATHINFO_EXTENSION);
			$fileName = str_replace('.'.$type,"",$newfile->getClientFilename()).'_'.time().'.'.$type;
			$name = md5(time());
			$attachment_settings = getAttachmentSettings($class);
			$file = $_FILES['file'];
			
			$file_formats = explode(",", $attachment_settings['allowed_file_formats']);
			$file_formats = array_map('trim', $file_formats);
			$max_file_size = $attachment_settings['allowed_file_size'];
			$kilobyte = 1024;
			$megabyte = $kilobyte * 1024;
			$file["type"] = get_mime($file['tmp_name']);  
			
			$current_file_size = round($file["size"] / $megabyte, 2);
			if (in_array($file["type"], $file_formats) || empty($attachment_settings['allowed_file_formats'])) {
				if (empty($max_file_size) || (!empty($max_file_size) && $current_file_size <= $max_file_size)) {
					if (!file_exists(APP_PATH . '/media/tmp/')) {
						mkdir(APP_PATH . '/media/tmp/',0777,true);
					}
					if ($type == 'php') {
						$type = 'txt';
					}
					if (move_uploaded_file($newfile->file, APP_PATH . '/media/tmp/' . $name . '.' . $type) === true) {
						$filename = $name . '.' . $type;
						if ($class == "UserProfile") {
							$category_id = isset($args['category_id']) ? $args['category_id']: null;
							saveImage('UserProfile', $filename, $user_category->id, true, $authUser->id, null);
						}
						$response = array(
							'attachment' => $filename,
							'error' => array(
								'code' => 0,
								'message' => 'Successfully uploaded'
							)
						);
					} else {
						$response = array(
							'error' => array(
								'code' => 1,
								'message' => 'Attachment could not be added.',
								'fields' => ''
							)
						);
					}
				} else {
					$response = array(
						'error' => array(
							'code' => 1,
							'message' => "The uploaded file size exceeds the allowed " . $attachment_settings['allowed_file_size'] . "MB",
							'fields' => ''
						)
					);
				}
			} else {
				$response = array(
					'error' => array(
						'code' => 1,
						'message' => "File couldn't be uploaded. Allowed extensions: " . $attachment_settings['allowed_file_extensions'],
						'fields' => ''
					)
				);
			}
		} else {
			$userFiles = Models\Attachment::where('foreign_id', $authUser->id)->where('class', 'UserProfile')->get()->toArray();
			$response = array(
				'data' => $userFiles,
				'error' => array(
					'code' => 0,
					'message' => ''
				)
			);
		}
		return renderWithJson($response);
	}
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->GET('/api/v1/sizes', function ($request, $response, $args) {
	$sizes = Models\Size::where('is_active', true)->get()->toArray();
	$results = array(
		'data' => $sizes
	);
	return renderWithJson($results, 'Sizes details list fetched successfully','', 0);
});
$app->GET('/api/v1/instant_vote', function ($request, $response, $args) {
	$queryParams = $request->getQueryParams();    
    global $authUser;
    $result = array();
    try {
        $enabledIncludes = array(
			'user'
		);
        $users = Models\UserContest::with($enabledIncludes)->orderBy('instant_votes', 'DESC')->get()->toArray();
		$max_limit = (!empty($users[0]) && $users[0]['instant_votes'] != 0) ? round($users[0]['instant_votes'],-2) : 0;  
        $result = array(
            'data' => $users,
			'max_limit' => $max_limit 
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
});
$app->GET('/api/v1/user_category/{userId}', function ($request, $response, $args) {
	$enabledIncludes = array(
					'category'
				);
	$user = Models\User::select('id')->Where('username', $request->getAttribute('userId'))->first();			
	$categories = Models\UserCategory::select('id', 'category_id')->with($enabledIncludes)->where('is_active', true)->where('user_id', $user->id)->get()->toArray();
	$results = array(
		'data' => $categories
	);
	return renderWithJson($results, 'Categories details list fetched successfully','', 0);
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->GET('/api/v1/admin/settings', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        if (empty($queryParams['sortby'])) {
            $queryParams['sortby'] = 'ASC';
        }
		$queryParams['is_active'] = true;
        $settingCategories = Models\SettingCategory::Filter($queryParams);
        // We are not implement Widget now, So we doen't return Widget data
        $settingCategories = $settingCategories->paginate($count)->toArray();
        $data = $settingCategories['data'];
        unset($settingCategories['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $settingCategories
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin'));
$app->GET('/api/v1/admin/settings/{id}', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	try {
		$queryParams = $request->getQueryParams();
		$result = array();
		$settings = Models\Setting::where('setting_category_id', $request->getAttribute('id'))->where('is_active', true)->get();	
		$result = array();
		$result['data'] = $settings;
		return renderWithJson($result, 'Success','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->PUT('/api/v1/admin/settings/{id}', function ($request, $response, $args) {
	$args = $request->getParsedBody();
	try {
		foreach ($args as $key=>$value) {
			Models\Setting::where('name', $key)->update(array(
				'value' => $value
			));
		}
		return renderWithJson(array(), 'Successfully updated','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/payment_gateways/{id}', function ($request, $response, $args) {
	global $authUser;
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $paymentGateways = Models\PaymentGateway::where('id', $request->getAttribute('id'))->first();
		$paymentGateways_model = new Models\PaymentGateway;
		$paymentGateways->makeVisible($paymentGateways_model->hidden);
		$paymentGateways = $paymentGateways->toArray();
		$data = array();
		if ($request->getAttribute('id') == 1) {
			$subarray = array();
			$subarray['name'] = 'sanbox_paypal_email';
			$subarray['label'] = 'Sanbox Paypal Email';
			$subarray['value'] = $paymentGateways['sanbox_paypal_email'];
			$subarray['is_required'] = true;
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
			$subarray = array();
			$subarray['name'] = 'sanbox_userid';
			$subarray['label'] = 'Sanbox Userid';
			$subarray['value'] = $paymentGateways['sanbox_userid'];
			$subarray['is_required'] = true;
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
			$subarray = array();
			$subarray['name'] = 'sanbox_password';
			$subarray['label'] = 'Sanbox Password';
			$subarray['is_required'] = true;
			$subarray['value'] = $paymentGateways['sanbox_password'];
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;			
			$subarray = array();
			$subarray['is_required'] = true;
			$subarray['name'] = 'sanbox_signature';
			$subarray['label'] = 'Sanbox Signature';
			$subarray['value'] = $paymentGateways['sanbox_signature'];
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
			$subarray = array();
			$subarray['is_required'] = true;
			$subarray['name'] = 'sanbox_application_id';
			$subarray['label'] = 'Sanbox Application Id';
			$subarray['value'] = $paymentGateways['sanbox_application_id'];
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
			$subarray = array();
			$subarray['name'] = 'live_paypal_email';
			$subarray['label'] = 'Live Paypal Email';
			$subarray['value'] = $paymentGateways['live_paypal_email'];
			$subarray['is_required'] = true;
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
			$subarray = array();
			$subarray['is_required'] = true;
			$subarray['name'] = 'live_userid';
			$subarray['label'] = 'Live Userid';
			$subarray['value'] = $paymentGateways['live_userid'];
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
			$subarray = array();
			$subarray['is_required'] = true;
			$subarray['name'] = 'live_password';
			$subarray['label'] = 'Live Password';
			$subarray['value'] = $paymentGateways['live_password'];
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;			
			$subarray = array();
			$subarray['is_required'] = true;
			$subarray['name'] = 'live_signature';
			$subarray['label'] = 'Live Signature';
			$subarray['value'] = $paymentGateways['live_signature'];
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
			$subarray = array();
			$subarray['is_required'] = true;
			$subarray['name'] = 'live_application_id';
			$subarray['label'] = 'Live Application Id';
			$subarray['value'] = $paymentGateways['live_application_id'];
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
			$subarray = array();
			$subarray['is_required'] = true;
			$subarray['name'] = 'paypal_more_ten';
			$subarray['label'] = 'Paypal Fee More Then 10$ percentage';
			$subarray['value'] = $paymentGateways['paypal_more_ten'];
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
			$subarray = array();
			$subarray['is_required'] = true;
			$subarray['name'] = 'paypal_more_ten_in_cents';
			$subarray['label'] = 'Paypal Fee More Then 10$ In cents';
			$subarray['value'] = $paymentGateways['paypal_more_ten_in_cents'];
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
			$subarray = array();
			$subarray['is_required'] = true;
			$subarray['name'] = 'paypal_less_ten';
			$subarray['label'] = 'Paypal Fee Less Then 10$ percentage';
			$subarray['value'] = $paymentGateways['paypal_less_ten'];
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
			$subarray = array();
			$subarray['is_required'] = true;
			$subarray['name'] = 'paypal_less_ten_in_cents';
			$subarray['label'] = 'Paypal Fee Less Then 10$ In cents';
			$subarray['value'] = $paymentGateways['paypal_less_ten_in_cents'];
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
		} else if ($request->getAttribute('id') == 2) {
			$subarray = array();
			$subarray['is_required'] = true;
			$subarray['name'] = 'sanbox_secret_key';
			$subarray['label'] = 'Sanbox Secret key';
			$subarray['value'] = $paymentGateways['sanbox_secret_key'];
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
			$subarray = array();
			$subarray['is_required'] = true;
			$subarray['name'] = 'sanbox_publish_key';
			$subarray['label'] = 'Sanbox Publish Key';
			$subarray['value'] = $paymentGateways['sanbox_publish_key'];
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
			$subarray = array();
			$subarray['is_required'] = true;
			$subarray['name'] = 'live_secret_key';
			$subarray['label'] = 'Live Secret Key';
			$subarray['value'] = $paymentGateways['live_secret_key'];
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
			$subarray = array();
			$subarray['is_required'] = true;
			$subarray['name'] = 'live_publish_key';
			$subarray['label'] = 'Live Publish Key';
			$subarray['value'] = $paymentGateways['live_publish_key'];
			$subarray['type'] = 'text';
			$subarray['edit'] = true;
			$data[] = $subarray;
		}
		$subarray = array();
		$subarray['name'] = 'is_test_mode';
		$subarray['label'] = 'Test Mode';
		$subarray['value'] = ($paymentGateways['is_test_mode'] == 1) ? true : false;
		$subarray['type'] = 'checkbox';
		$subarray['edit'] = true;
		$data[] = $subarray;
		
		$subarray = array();
		$subarray['name'] = 'is_active';
		$subarray['label'] = 'Active';
		$subarray['value'] = ($paymentGateways['is_active'] == 1) ? true : false;
		$subarray['type'] = 'checkbox';
		$subarray['edit'] = true;
		$data[] = $subarray;
		
		$result = array(
            'data' => $data
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->PUT('/api/v1/admin/payment_gateways/{id}', function ($request, $response, $args) {
	$args = $request->getParsedBody();
	try {
		Models\PaymentGateway::where('id', $request->getAttribute('id'))->update($args);
		return renderWithJson(array(), 'Successfully updated','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/payment_gateways', function ($request, $response, $args) {
	global $authUser;
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $paymentGateways = Models\PaymentGateway::with('attachment')->get()->toArray();
		$result = array(
            'data' => $paymentGateways
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/pages', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $pages = Models\Page::Filter($queryParams)->paginate($count)->toArray();
        $data = $pages['data'];
        unset($pages['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $pages
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->GET('/api/v1/admin/pages/{id}', function ($request, $response, $args) {
    global $authUser, $_server_domain_url;
	try {
		$queryParams = $request->getQueryParams();
		$result = array();
		$page = Models\Page::where('id', $request->getAttribute('id'))->first();
		$result = array();
		$result['data'] = $page;
		return renderWithJson($result, 'Success','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->PUT('/api/v1/admin/pages/{id}', function ($request, $response, $args) {
    global $authUser, $_server_domain_url;
	$args = $request->getParsedBody();
	try {
		Models\Page::where('id', $request->getAttribute('id'))->update(array(
			'content' => $args['content'],
			'title' => $args['title'],
			'dispaly_url' => $args['dispaly_url'],
			'url' => '/page/'.$args['dispaly_url']
		));
		return renderWithJson(array(), 'Successfully updated','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->GET('/api/v1/admin/sizes', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	$queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
		$queryParams['is_active'] = true;
        $respones = Models\Size::Filter($queryParams)->paginate($count);
		$respones = $respones->toArray();
        $data = $respones['data'];
        unset($respones['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $respones
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/sizes/{id}', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	try {
		$queryParams = $request->getQueryParams();
		$result = array();
		$size = Models\Size::where('id', $request->getAttribute('id'))->first();
		$result = array();
		$result['data'] = $size;
		return renderWithJson($result, 'Success','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->POST('/api/v1/admin/sizes', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	$args = $request->getParsedBody();
	$result = array();
    try {
        $size = new Models\Size;
		$size->name = $args['name'];
		$size->is_active = true;
		$size->save();
        return renderWithJson($result, 'Successfully added','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $e->getMessage(), $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->PUT('/api/v1/admin/sizes/{id}', function ($request, $response, $args) {
	$args = $request->getParsedBody();
	try {
		Models\Size::where('id', $request->getAttribute('id'))->update(array(
			'name' => $args['name']
		));
		return renderWithJson(array(), 'Successfully updated','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->PUT('/api/v1/admin/sizes/delete/{id}', function ($request, $response, $args) {
	$args = $request->getParsedBody();
	try {
		Models\Size::where('id', $request->getAttribute('id'))->update(array(
			'is_active' => false
		));
		return renderWithJson(array(), 'Successfully delete','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/categories', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	$queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
		$queryParams['is_active'] = true;
        $respones = Models\Category::Filter($queryParams)->paginate($count);
		$respones = $respones->toArray();
        $data = $respones['data'];
        unset($respones['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $respones
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/timezones', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	$queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = 1000;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
		$queryParams['is_active'] = true;
        $respones = Models\Timezone::select('id', 'name')->Filter($queryParams)->paginate($count);
		$respones = $respones->toArray();
        $data = $respones['data'];
        unset($respones['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $respones
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $e->getMessage(), $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/categories/{id}', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	try {
		$queryParams = $request->getQueryParams();
		$result = array();
		$category = Models\Category::where('id', $request->getAttribute('id'))->first();
		$result = array();
		$result['data'] = $category;
		return renderWithJson($result, 'Success','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->POST('/api/v1/admin/categories', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	$args = $request->getParsedBody();
	$result = array();
    try {
        $category = new Models\Category;
		$category->name = $args['name'];
		$category->is_active = true;
		$category->save();
        return renderWithJson(array(), 'Successfully added','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $e->getMessage(), $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->PUT('/api/v1/admin/categories/{id}', function ($request, $response, $args) {
	$args = $request->getParsedBody();
	try {
		Models\Category::where('id', $request->getAttribute('id'))->update(array(
			'name' => $args['name']
		));
		return renderWithJson(array(), 'Successfully updated','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->PUT('/api/v1/admin/categories/delete/{id}', function ($request, $response, $args) {
	$args = $request->getParsedBody();
	try {
		Models\Category::where('id', $request->getAttribute('id'))->update(array(
			'is_active' => false
		));
		return renderWithJson(array(), 'Successfully delete','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/votes', function ($request, $response, $args) {
	$queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $votes = Models\UserCategory::with(array('category', 'user'))->Filter($queryParams)->paginate($count)->toArray();
        $data = $votes['data'];
        unset($votes['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $votes
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/instant_votes', function ($request, $response, $args) {
	$queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
		$queryParams['role_id'] = \Constants\ConstUserTypes::Employer;
        $votes = Models\User::select('id', 'first_name' , 'last_name', 'votes')-> Filter($queryParams)->paginate($count)->toArray();
        $data = $votes['data'];
        unset($votes['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $votes
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/email_templates', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $emailTemplates = Models\EmailTemplate::Filter($queryParams)->paginate($count)->toArray();
        $data = $emailTemplates['data'];
        unset($emailTemplates['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $emailTemplates
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin'));
$app->GET('/api/v1/admin/email_templates/{id}', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	try {
		$queryParams = $request->getQueryParams();
		$result = array();
		$emailTemplate = Models\EmailTemplate::where('id', $request->getAttribute('id'))->first();
		$result = array();
		$result['data'] = $emailTemplate;
		return renderWithJson($result, 'Success','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->PUT('/api/v1/admin/email_templates/{id}', function ($request, $response, $args) {
	$args = $request->getParsedBody();
	try {
		Models\EmailTemplate::where('id', $request->getAttribute('id'))->update(array(
			'subject' => $args['subject'],
			'html_email_content' => $args['html_email_content'],
			'description' => $args['description'],
		));
		return renderWithJson(array(), 'Successfully updated','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/vote_packages', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	$queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
		$queryParams['is_active'] =true;
        $respones = Models\VotePackage::Filter($queryParams)->paginate($count);
		$respones = $respones->toArray();
        $data = $respones['data'];
        unset($respones['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $respones
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/vote_packages/{id}', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	try {
		$queryParams = $request->getQueryParams();
		$result = array();
		$votePackage = Models\VotePackage::where('id', $request->getAttribute('id'))->first();
		$result = array();
		$result['data'] = $votePackage;
		return renderWithJson($result, 'Success','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->POST('/api/v1/admin/vote_packages', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	$args = $request->getParsedBody();
	$result = array();
    try {
        $votePackage = new Models\VotePackage;
		$votePackage->name = $args['name'];
		$votePackage->price = $args['price'];
		$votePackage->vote = $args['vote'];
		$votePackage->description = $args['description'];
		$votePackage->is_active = true;
		$votePackage->save();
        return renderWithJson(array(), 'Successfully added','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $e->getMessage(), $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->PUT('/api/v1/admin/vote_packages/{id}', function ($request, $response, $args) {
	$args = $request->getParsedBody();
	try {
		Models\VotePackage::where('id', $request->getAttribute('id'))->update(array(
			'name' => $args['name'],
			'price' => $args['price'],
			'vote' => $args['vote'],
			'description' => $args['description'],
		));
		return renderWithJson(array(), 'Successfully updated','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->PUT('/api/v1/admin/vote_packages/delete/{id}', function ($request, $response, $args) {
	$args = $request->getParsedBody();
	try {
		Models\VotePackage::where('id', $request->getAttribute('id'))->update(array(
			'is_active' => false
		));
		return renderWithJson(array(), 'Successfully delete','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/subscriptions', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	$queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
		$queryParams['is_active'] =true;
        $respones = Models\Subscription::Filter($queryParams)->paginate($count);
		$respones = $respones->toArray();
        $data = $respones['data'];
        unset($respones['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $respones
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/subscriptions/{id}', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	try {
		$queryParams = $request->getQueryParams();
		$result = array();
		$subscription = Models\Subscription::where('id', $request->getAttribute('id'))->first();
		$result = array();
		$result['data'] = $subscription;
		return renderWithJson($result, 'Success','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->PUT('/api/v1/admin/subscriptions/{id}', function ($request, $response, $args) {
	$args = $request->getParsedBody();
	try {
		Models\Subscription::where('id', $request->getAttribute('id'))->update(array(
			'price' => $args['price'],
			'days' => $args['days'],
			'description' => $args['description'],
		));
		return renderWithJson(array(), 'Successfully updated','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/users', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	$queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
		$enabledIncludes = array(
			'address'
		);
		$queryParams['is_active'] = true;
		if (!empty($queryParams['class'])) {
			if ($queryParams['class'] === 'companies') {
				$queryParams['role_id'] = \Constants\ConstUserTypes::Company;
			} else if ($queryParams['class'] === 'contestants') {
				$queryParams['role_id'] = \Constants\ConstUserTypes::Employer;
			} else {
				$queryParams['role_id'] = \Constants\ConstUserTypes::User;
			}
			unset($queryParams['class']);
		}
        $respones = Models\User::with($enabledIncludes)->Filter($queryParams)->paginate($count);
		$user_model = new Models\User;
		$respones->makeVisible($user_model->hidden);
		$respones = $respones->toArray();
        $data = $respones['data'];
        unset($respones['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $respones
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/users/{userId}', function ($request, $response, $args) {
	try {
		$queryParams = $request->getQueryParams();
		$result = array();
		$enabledIncludes = array(
			'attachment',
			'address',
			'user_categories'
		);
		$_GET['user_id'] = $request->getAttribute('userId');
		$user = Models\User::with($enabledIncludes)->where('id', $request->getAttribute('userId'))->first();
		$user->makeVisible(['email']);
		$user = $user->toArray();
		$user['category'] = array();
		if (!empty($user['user_categories'])) {
			foreach($user['user_categories'] as $cat) {
				$user['category'][] = $cat['category'];
			}
			unset($user['user_categories']);
		}
		$result = array();
		$result['data'] = $user;
		return renderWithJson($result, 'Success','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->POST('/api/v1/admin/users', function ($request, $response, $args) {
	global $authUser;
	try {
		$args = $request->getParsedBody();
		$queryParams = $request->getQueryParams();
		$role_id = \Constants\ConstUserTypes::User;
		$company_id = 0;
		if ($queryParams['class'] === 'companies') {
			$role_id = \Constants\ConstUserTypes::Company;
		} else if ($queryParams['class'] === 'contestants') {
			$role_id = \Constants\ConstUserTypes::Employer;
			$company_id = $authUser->id;
		}
		$result = addAdminUser($args, $role_id, $company_id);
		return renderWithJson(array(), $result['message'],'', $result['code']);
	} catch (Exception $e) {
        return renderWithJson(array(), 'No record found.'.$e->getMessage(), '', 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->PUT('/api/v1/admin/users/delete/{userId}', function ($request, $response, $args) {
	global $authUser;
	$userId = $request->getAttribute('userId');
	if ($userId != 1) {
		Models\User::where('id', $userId)->update(array(
			'is_active' => false
		));
		return renderWithJson(array(), 'User is successfully deleted','', 0);
	}
	return renderWithJson(array(), 'User could not be deleted','', 1);
})->add(new ACL('canAdmin canCompanyUser'));
$app->PUT('/api/v1/admin/users/{userId}', function ($request, $response, $args) {
	global $authUser;
	$args = $request->getParsedBody();
	$image = $args['image'];
	$cover_photo = $args['cover_photo'];
	$addressDetail = $args['address'];
	$categories = $args['category'];
	unset($args['category']);
	$userId = $request->getAttribute('userId');
	unset($args['id']);
	unset($args['image']);
	unset($args['cover_photo']);
	unset($args['address']);
	$user = Models\User::find($userId);
	$user->fill($args);
	$result = array();
	try {
		$validationErrorFields = $user->validate($args);
		if (empty($validationErrorFields)) {
			$user->save();
			if (isset($image) && $image != '') {
				saveImage('UserAvatar', $image, $userId);
			}
			if (isset($cover_photo) && $cover_photo != '') {
				saveImage('CoverPhoto', $cover_photo, $userId);
			}
			if (!empty($categories) && $user->role_id === \Constants\ConstUserTypes::Employer) {
				$category_ids = array_column($categories, 'id');
				$userCategoryId = Models\UserCategory::where('user_id', $userId)->get()->toArray();
				$userCategoryIds = array_column($userCategoryId, 'category_id');
				$adds = array_diff($category_ids,$userCategoryIds);
				$deletes = array_diff($userCategoryIds,$category_ids);
				if (!empty($adds)) {
					foreach ($adds as $add) {
						$userCategory = new Models\UserCategory;
						$userCategory->user_id = $userId;
						$userCategory->category_id = $add;
						$userCategory->save();
					}
				}
				if (!empty($deletes)) {
					foreach ($userCategoryId as $userCatId) {
						if(in_array($userCatId['category_id'] , $deletes)) {
							$isActive = ($userCatId['is_active'] == false) ? true : false;
							Models\UserCategory::where('user_id', $userId)->whereIn('category_id', $deletes)->update(array(
								'is_active'=> $isActive
							));
						}
					}
				}
			}
			if (isset($addressDetail) && $addressDetail != '') {
				$count = Models\UserAddress::where('user_id', $userId)->where('is_default', true)->count();
				if ($count > 1) {
					Models\UserAddress::where('user_id', $userId)->where('is_default', true)->update($addressDetail);
				} else {
					$address = new Models\UserAddress;
					$address->addressline1 = $addressDetail['addressline1'];
					$address->addressline2 = $addressDetail['addressline2'];
					$address->city = $addressDetail['city'];
					$address->state = $addressDetail['state'];
					$address->country = $addressDetail['country'];
					$address->zipcode = $addressDetail['zipcode'];
					$address->user_id = $user->id;
					$address->is_default = true;
					$address->name = 'Default';
					$address->save();
				}
			}
			$result = $user->toArray();
			return renderWithJson($result, 'Success','', 0);
		} else {
			return renderWithJson($result, 'User could not be updated. Please, try again.', $validationErrorFields, 1);
		}
	} catch (Exception $e) {
		return renderWithJson(array(), 'User could not be updated. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/contests', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	$queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $respones = Models\Contest::where('type_id', 1)->where('is_active', 1)->Filter($queryParams)->paginate($count);
		$respones = $respones->toArray();
        $data = $respones['data'];
        unset($respones['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $respones
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->POST('/api/v1/admin/contests', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	$args = $request->getParsedBody();
	$result = array();
    try {
		$count = Models\Contest::where('type_id', 1)->where('name', $args['name'])->count();
		if ($count > 1) {
			return renderWithJson(array(), 'Contest name already exist','', 1);
		} else {
			$start_date =  date("Y-m-d", strtotime($args['start_date']['year'].'-'.$args['start_date']['month'].'-'.$args['start_date']['day']));
			$end_date =  date("Y-m-d", strtotime($args['end_date']['year'].'-'.$args['end_date']['month'].'-'.$args['end_date']['day']));
			if (strtotime($start_date) > strtotime($end_date)) {
				return renderWithJson(array(), 'Contest start date should be greater than end date','', 1);
			} else {
				$start_date = $start_date. ' 00:00:00';
				$end_date = $end_date. ' 00:00:00';
				$start = Models\Contest::where('type_id', 1)->where('is_active', 1)->where('start_date', '>=', $start_date)->where('start_date', '<=', $end_date)->count();
				$end = Models\Contest::where('type_id', 1)->where('is_active', 1)->whereDate('end_date', '>=', $end_date)->whereDate('end_date', '<=', $end_date)->count();
				if ($start > 0 || $end > 0) {
					return renderWithJson(array(), 'Contest already exist in this date range','', 1);
				} else {
					$contest = new Models\Contest;
					$contest->type_id = 1;
					$contest->user_id = $authUser->id;
					$contest->name = $args['name'];
					$contest->start_date = $start_date;
					$contest->end_date = $end_date;
					$contest->is_active = true;
					$contest->save();
					return renderWithJson($result, 'Successfully added','', 0);
				}
			}
		}
		
    } catch (Exception $e) {
        return renderWithJson($result, $e->getMessage(), $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/instants', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	$queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
		$queryParams['type_id'] = 2;
        $respones = Models\Contest::where('type_id', 2)->where('is_active', 1)->Filter($queryParams)->paginate($count);
		$respones = $respones->toArray();
        $data = $respones['data'];
        unset($respones['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $respones
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->POST('/api/v1/admin/instants', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	$args = $request->getParsedBody();
	$result = array();
    try {
		$count = Models\Contest::where('type_id', 2)->where('name', $args['name'])->count();
		if ($count > 1) {
			return renderWithJson(array(), 'Contest name already exist','', 1);
		} else {
			$start_date =  date("Y-m-d", strtotime($args['start_date']['year'].'-'.$args['start_date']['month'].'-'.$args['start_date']['day']));
			$end_date =  date("Y-m-d", strtotime($args['end_date']['year'].'-'.$args['end_date']['month'].'-'.$args['end_date']['day']));
			if (strtotime($start_date) > strtotime($end_date)) {
				return renderWithJson(array(), 'Contest start date should be greater than end date','', 1);
			} else {
				$start_date = $start_date. ' 00:00:00';
				$end_date = $end_date. ' 00:00:00';
				$start = Models\Contest::where('type_id', 2)->where('is_active', 1)->where('start_date', '>=', $start_date)->where('start_date', '<=', $end_date)->count();
				$end = Models\Contest::where('type_id', 2)->where('is_active', 1)->whereDate('end_date', '>=', $end_date)->whereDate('end_date', '<=', $end_date)->count();
				if ($start > 0 || $end > 0) {
					return renderWithJson(array(), 'Contest already exist in this date range','', 1);
				} else {
					$contest = new Models\Contest;
					$contest->type_id = 2;
					$contest->user_id = $authUser->id;
					$contest->name = $args['name'];
					$contest->start_date = $start_date;
					$contest->end_date = $end_date;
					$contest->is_active = true;
					$contest->save();
					return renderWithJson($result, 'Successfully added','', 0);
				}
			}
		}
		
    } catch (Exception $e) {
        return renderWithJson($result, $e->getMessage(), $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/instant_contestants', function ($request, $response, $args) {
	$queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
		$enabledIncludes = array(
						'user',
						'contest'
					);
		$respones = Models\UserContest::with($enabledIncludes)->Filter($queryParams)->paginate($count);
		$respones = $respones->toArray();
        $data = $respones['data'];
        unset($respones['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $respones
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/instants/{id}', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	try {
		$queryParams = $request->getQueryParams();
		$result = array();
		$instants = Models\Contest::where('id', $request->getAttribute('id'))->first();
		$instants->makeVisible(['email']);
		$result = array();
		$result['data'] = $instants;
		return renderWithJson($result, 'Success','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->POST('/api/v1/admin/instant_contestants', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	$args = $request->getParsedBody();
	$result = array();
    try {
		$contestId = isset($args['name']['id']) ? $args['name']['id'] : $args['name'];
		$userId = isset($args['first_name']['id']) ? $args['first_name']['id'] : $args['first_name'];
		$count = Models\UserContest::where('type_id', 2)->where('contest_id', $contestId)->where('user_id', $userId)->count();
		if ($count > 1) {
			return renderWithJson(array(), 'Contestant already exist','', 1);
		} else {
			$contest = new Models\UserContest;
			$contest->type_id = 2;
			$contest->contest_id = $contestId;
			$contest->user_id = $userId;
			$contest->save();
			return renderWithJson($result, 'Successfully added','', 0);
		}
		
    } catch (Exception $e) {
        return renderWithJson($result, $e->getMessage(), $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->POST('/api/v1/admin/instants/add', function ($request, $response, $args) {
	try {
		$args = $request->getParsedBody();
		$result = addAdminUser($args, \Constants\ConstUserTypes::User, 0);
		return renderWithJson(array(), $result['message'],'', $result['code']);
	} catch (Exception $e) {
        return renderWithJson(array(), 'No record found.'.$e->getMessage(), '', 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->PUT('/api/v1/admin/instants/{userId}', function ($request, $response, $args) {
	global $authUser;
	$args = $request->getParsedBody();
	$image = $args['image'];
	$cover_photo = $args['cover_photo'];
	$addressDetail = $args['address'];
	$userId = $request->getAttribute('userId');
	unset($args['id']);
	unset($args['image']);
	unset($args['cover_photo']);
	unset($args['address']);
	$user = Models\User::find($userId);
	$user->fill($args);
	$result = array();
	try {
		$validationErrorFields = $user->validate($args);
		if (empty($validationErrorFields)) {
			$user->save();
			if (isset($image) && $image != '') {
				saveImage('UserAvatar', $image, $userId);
			}
			if (isset($cover_photo) && $cover_photo != '') {
				saveImage('CoverPhoto', $cover_photo, $userId);
			}
			if (isset($addressDetail) && $addressDetail != '') {
				$count = Models\UserAddress::where('user_id', $userId)->where('is_default', true)->count();
				if ($count > 1) {
					Models\UserAddress::where('user_id', $userId)->where('is_default', true)->update($addressDetail);
				} else {
					$address = new Models\UserAddress;
					$address->addressline1 = $addressDetail['addressline1'];
					$address->addressline2 = $addressDetail['addressline2'];
					$address->city = $addressDetail['city'];
					$address->state = $addressDetail['state'];
					$address->country = $addressDetail['country'];
					$address->zipcode = $addressDetail['zipcode'];
					$address->user_id = $user->id;
					$address->is_default = true;
					$address->name = 'Default';
					$address->save();
				}
			}
			$result = $user->toArray();
			return renderWithJson($result, 'Success','', 0);
		} else {
			return renderWithJson($result, 'User could not be updated. Please, try again.', $validationErrorFields, 1);
		}
	} catch (Exception $e) {
		return renderWithJson(array(), 'User could not be updated. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/approvals', function ($request, $response, $args) {    
    $queryParams = $request->getQueryParams();
    global $authUser;
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
		$enabledIncludes = array(
			'user',
			'user_category'
		);
        $queryParams['is_admin_approval'] = 1;
		$queryParams['class'] = 'UserProfile';
		$attachments = Models\Attachment::with($enabledIncludes)->Filter($queryParams)->paginate($count);
        $attachments = $attachments->toArray();
        $data = $attachments['data'];
		unset($attachments['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $attachments
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/approvals/{id}', function ($request, $response, $args) {
	$enabledIncludes = array(
			'user',
			'user_category',
			'thumb'
		);
	$att = Models\Attachment::with($enabledIncludes)->where('is_admin_approval', 1)->where('id', $request->getAttribute('id'))->get()->toArray();
	$att = current($att);
	$data = array();
	$data['user'] = $att['user'];
	$data['user_category'] = $att['user_category'];
	unset($att['user']);
	unset($att['user_category']);
	$data['attachment'] = $att;
	$results = array(
		'data' => $data
	);
	return renderWithJson($results, 'Attachment detail fetched successfully','', 0);
})->add(new ACL('canAdmin canCompanyUser'));
$app->PUT('/api/v1/admin/approvals/{id}', function ($request, $response, $args) {
	$queryParams = $request->getQueryParams();
	if ($request->getAttribute('id') != '') {
		global $authUser;
		if ($queryParams['class'] == 'approve') {
				Models\Attachment::where('is_admin_approval', 1)->where('id', $request->getAttribute('id'))->update(array(
						'is_admin_approval' => 2,
						'approved_user_id' => $authUser->id
					));
			return renderWithJson(array(), 'Approved successfully','', 0);
		} else {
			Models\Attachment::where('is_admin_approval', 1)->where('id', $request->getAttribute('id'))->update(array(
						'is_admin_approval' => 3,
						'approved_user_id' => $authUser->id
					));
			return renderWithJson(array(), 'Disapproved successfully','', 0);
		}
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->PUT('/api/v1/admin/approvals', function ($request, $response, $args) {
	$queryParams = $request->getQueryParams();
	global $authUser;
	if ($queryParams['class'] == 'approve') {
			Models\Attachment::where('is_admin_approval', 1)->update(array(
					'is_admin_approval' => 2,
					'approved_user_id' => $authUser->id
				));
		return renderWithJson(array(), 'Approved successfully','', 0);
	} else {
		Models\Attachment::where('is_admin_approval', 1)->update(array(
					'is_admin_approval' => 3,
					'approved_user_id' => $authUser->id
				));
		return renderWithJson(array(), 'Disapproved successfully','', 0);
	}
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/track_orders', function ($request, $response, $args) {    
    $queryParams = $request->getQueryParams();
    global $authUser;
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
		$enabledIncludes = array(
                    'detail',
					'size',
					'coupon',
					'user'
                );
		// ->where('user_id', $authUser->id)
		$carts = Models\Cart::with($enabledIncludes)->where('is_purchase', true)->Filter($queryParams)->paginate($count);
		$carts->makeVisible(['otp', 'invoice_no', 'shipping_status']);
        $carts = $carts->toArray();
        $data = $carts['data'];
		unset($carts['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $carts
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, 'No record found.'.$e->getMessage(), '', 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/track_orders/{id}', function ($request, $response, $args) {    
    $queryParams = $request->getQueryParams();
    global $authUser;
    $result = array();
    try {
        $enabledIncludes = array(
                    'detail',
					'size',
					'coupon',
					'user'
                );
		$carts = Models\Cart::with($enabledIncludes)->where('is_purchase', true)->where('id', $request->getAttribute('id'))->first();
		$carts->makeVisible(['otp', 'invoice_no', 'shipping_status']);
		$carts = $carts->toArray();
        $result = array(
            'data' => $carts
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson(array(), 'No record found.'.$e->getMessage(), '', 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->PUT('/api/v1/admin/track_orders/{id}', function ($request, $response, $args) {    
    $queryParams = $request->getQueryParams();
	$args = $request->getParsedBody();
    global $authUser;
    $result = array();
    try {
        Models\Cart::where('id', $request->getAttribute('id'))->update(array(
						'shipping_status' => $args['shipping_status']
					));
		return renderWithJson(array(), 'Updated successfully','', 0);
    } catch (Exception $e) {
        return renderWithJson(array(), 'No record found.'.$e->getMessage(), '', 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/transactions/{id}', function ($request, $response, $args) {    
    $queryParams = $request->getQueryParams();
	$args = $request->getParsedBody();
    global $authUser;
    $result = array();
    try {
        $enabledIncludes = array(
			'user',
            'other_user',
			'parent_user',
			'payment_gateway',
			'detail',
			'package',
			'subscription'
		);
		$transactions = Models\Transaction::select('id','created_at', 'user_id', 'to_user_id', 'parent_user_id', 'foreign_id','payment_gateway_id', 'amount')->where('id', $request->getAttribute('id'))->with($enabledIncludes)->first();
		$result = array(
            'data' => $transactions
        );
        return renderWithJson($result);
		return renderWithJson($transactions);
    } catch (Exception $e) {
        return renderWithJson(array(), 'No record found.'.$e->getMessage(), '', 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
$app->GET('/api/v1/admin/transactions', function ($request, $response, $args) {    
	global $authUser;
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $enabledIncludes = array(
			'user',
            'other_user',
			'parent_user',
			'payment_gateway'
		);
		if (!empty($queryParams['class'])) {
			if ($queryParams['class'] == 'Product') {
				$enabledIncludes = array_merge($enabledIncludes,array('detail'));
			} else if ($queryParams['class'] == 'VotePackage' || $queryParams['class'] == 'InstantPackage') {
				$enabledIncludes = array_merge($enabledIncludes,array('package'));
			} else if ($queryParams['class'] == 'SubscriptionPackage') {
				$enabledIncludes = array_merge($enabledIncludes,array('subscription'));
			}
        }
        $transactions = Models\Transaction::select('id','created_at', 'user_id', 'to_user_id', 'parent_user_id', 'foreign_id','payment_gateway_id', 'amount')->with($enabledIncludes);
		if (!empty($authUser['id'])) {
            $user_id = $authUser['id'];
            $transactions->where(function ($q) use ($user_id) {
                $q->where('user_id', $user_id)->orWhere('to_user_id', $user_id);
            });
        }
		$transactions = $transactions->Filter($queryParams)->paginate($count);
		$transactionsNew = $transactions;
        $transactionsNew = $transactionsNew->toArray();
        $data = $transactionsNew['data'];
        unset($transactionsNew['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $transactionsNew
        );
        return renderWithJson($result);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canCompanyUser'));
/**
 * GET ipsGet
 * Summary: Fetch all ips
 * Notes: Returns all ips from the system
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/ips', function ($request, $response, $args) {
    global $authUser;
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $enabledIncludes = array(
            'timezone'
        );
        $ips = Models\Ip::with($enabledIncludes)->Filter($queryParams)->paginate($count)->toArray();
        $data = $ips['data'];
        unset($ips['data']);
        $results = array(
            'data' => $data,
            '_metadata' => $ips
        );
        return renderWithJson($results, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin'));
/**
 * DELETE IpsIdDelete
 * Summary: Delete ip
 * Notes: Deletes a single ip based on the ID supplied
 * Output-Formats: [application/json]
 */
$app->DELETE('/api/v1/ips/{ipId}', function ($request, $response, $args) {
    global $authUser;
    $ip = Models\Ip::find($request->getAttribute('ipId'));
    $result = array();
    try {
        if (!empty($ip)) {
            $ip->delete();
            $result = array(
                'status' => 'success',
            );
            return renderWithJson($result, 'Success','', 0);
        } else {
            return renderWithJson($result, 'Ip could not be deleted. Please, try again.', '', 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Ip could not be deleted. Please, try again.', '', 1);
    }
})->add(new ACL('canAdmin'));
/**
 * GET ipIdGet
 * Summary: Fetch ip
 * Notes: Returns a ip based on a single ID
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/ips/{ipId}', function ($request, $response, $args) {
    global $authUser;
    $result = array();
    $enabledIncludes = array(
        'timezone'
    );
    $ip = Models\Ip::with($enabledIncludes)->find($request->getAttribute('ipId'));
    if (!empty($ip)) {
        $result['data'] = $ip;
        return renderWithJson($result, 'Success','', 0);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
})->add(new ACL('canAdmin'));
$app->GET('/api/v1/cron', function ($request, $response, $args) use ($app)
{
	//Token clean up 
	$now = date('Y-m-d h:i:s');
	Models\OauthAccessToken::where('expires', '<=', $now)->delete();
	Models\OauthRefreshToken::where('expires', '<=', $now)->delete();
});
$app->GET('/api/v1/advertisements', function ($request, $response, $args) {
    global $authUser;
	$queryParams = $request->getQueryParams();
    $results = array();
    try {
		$count = PAGE_LIMIT;
		if (!empty($queryParams['limit'])) {
			$count = $queryParams['limit'];
		}
		$advertisements = Models\Advertisement::with('user', 'attachment')->Filter($queryParams)->paginate($count)->toArray();
		$data = $advertisements['data'];
		unset($advertisements['data']);
		$results = array(
            'data' => $data,
            '_metadata' => $advertisements
        );
		return renderWithJson($results, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
});
$app->GET('/api/v1/advertisement/{id}', function ($request, $response, $args) {
    global $authUser;
	
	$queryParams = $request->getQueryParams();
    $results = array();
    try {
        $advertisement = Models\Advertisement::find($request->getAttribute('id'));
        if (!empty($advertisement)) {
            $result['data'] = $advertisement;
            return renderWithJson($result, 'Success','', 0);
        } else {
            return renderWithJson($result, 'No record found', '', 1);
        }
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin'));
$app->POST('/api/v1/advertisement', function ($request, $response, $args) {
    global $authUser, $_server_domain_url;
	$result = array();
    $args = $request->getParsedBody();
    $advertisement = new Models\Advertisement($args);
    try {
        $validationErrorFields = $advertisement->validate($args);
        if (empty($validationErrorFields)) {
            $advertisement->is_active = 1;
            $advertisement->user_id = $authUser->id;
            if ($authUser['role_id'] == \Constants\ConstUserTypes::Admin && !empty($args['user_id'])) {
                $advertisement->user_id = $args['user_id'];
            }
            if ($advertisement->save()) {
				if ($advertisement->id) {
					if (!empty($args['image'])) {
						saveImage('Advertisement', $args['image'], $advertisement->id);
					}
					$result['data'] = $advertisement->toArray();
					return renderWithJson($result, 'Success','', 0);
				}
            } else {
				return renderWithJson($result, 'Advertisement could not be added. Please, try again.', '', 1);
			}
        } else {
            return renderWithJson($result, 'Advertisement could not be added. Please, try again.', $validationErrorFields, 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Advertisement could not be added. Please, try again.'.$e->getMessage(), '', 1);
    }
})->add(new ACL('canAdmin'));
$app->PUT('/api/v1/advertisement/{id}', function ($request, $response, $args) {
    global $authUser;
	$args = $request->getParsedBody();
	$advertisement = Models\Advertisement::find($request->getAttribute('id'));
	$advertisement->fill($args);
	$result = array();
	try {
		$validationErrorFields = $advertisement->validate($args);
		if (empty($validationErrorFields)) {
			$advertisement->save();
			if (!empty($args['image']) && $advertisement->id) {
				saveImage('Advertisement', $args['image'], $request->getAttribute('id'));
			}
			$result = $advertisement->toArray();
			return renderWithJson($result, 'Success','', 0);
		} else {
			return renderWithJson($result, 'Advertisement could not be updated. Please, try again.', $validationErrorFields, 1);
		}
	} catch (Exception $e) {
		return renderWithJson($result, 'Advertisement could not be updated. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin'));
$app->DELETE('/api/v1/advertisement/{id}', function ($request, $response, $args) {
    global $authUser;
	$args = array();
	$args['is_active'] = false;
	$advertisement = Models\Advertisement::find($request->getAttribute('id'));
	$advertisement->fill($args);
	$result = array();
	try {
		$advertisement->save();
		return renderWithJson(array(), 'Advertisement delete successfully','', 0);
	} catch (Exception $e) {
		return renderWithJson($result, 'Advertisement could not be delete. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin'));
$app->GET('/api/v1/user_address', function ($request, $response, $args) {
    global $authUser;
	$queryParams = $request->getQueryParams();
    $results = array();
    try {
		$count = PAGE_LIMIT;
		if (!empty($queryParams['limit'])) {
			$count = $queryParams['limit'];
		}
		$userAddress = Models\UserAddress::where('user_id', $authUser->id)->where('is_active', true)->get()->toArray();
		$results = array(
            'data' => $userAddress
        );
		return renderWithJson($results, 'Address details list fetched successfully','', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->GET('/api/v1/user_address/{id}', function ($request, $response, $args) {
    global $authUser;
	
	$queryParams = $request->getQueryParams();
    $results = array();
    try {
        $userAddress = Models\UserAddress::find($request->getAttribute('id'));
        if (!empty($userAddress)) {
            $result['data'] = $userAddress;
            return renderWithJson($result, 'Success','', 0);
        } else {
            return renderWithJson($result, 'No record found', '', 1);
        }
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->POST('/api/v1/user_address', function ($request, $response, $args) {
    global $authUser, $_server_domain_url;
	$result = array();
    $args = $request->getParsedBody();
	if (isset($args['id']) && $args['id'] !== 0) {
		$userAddress = Models\UserAddress::find($args['id']);
		$userAddress->fill($args);
		$result = array();
		try {
			$validationErrorFields = $userAddress->validate($args);
			if (empty($validationErrorFields)) {
				if ($args['is_default'] && $args['is_default'] == 1) {
					Models\UserAddress::where('user_id', $authUser->id)->update(array(
						'is_default' => 0
					));
				}
				Models\UserAddress::where('user_id', $authUser->id)->where('id', $request->getAttribute('id'))->update($args);
				$result['data'] = $userAddress->toArray();
				return renderWithJson($result, 'Address details updated successfully','', 0);
			} else {
				return renderWithJson($result, 'Address details could not be updated. Please, try again.', $validationErrorFields, 1);
			}
		} catch (Exception $e) {
			return renderWithJson($result, 'Address details could not be updated. Please, try again.', $e->getMessage(), 1);
		}
	} else {
		unset($args['id']);
		$userAddress = new Models\UserAddress($args);
		try {
			$validationErrorFields = $userAddress->validate($args);
			if (empty($validationErrorFields)) {
				$userAddress->is_active = 1;
				if ($userAddress->is_default && $userAddress->is_default == 1) {
					Models\UserAddress::where('user_id', $authUser->id)->update(array(
						'is_default' => 0
					));
					$userAddress->is_default = 1;
				} else {
					$userAddress->is_default = 0;
				}
				$userAddress->user_id = $authUser->id;
				if ($userAddress->save()) {
					$result['data'] = $userAddress->toArray();
					return renderWithJson($result, 'Success','', 0);
				} else {
					return renderWithJson($result, 'Address details could not be added. Please, try again.', '', 1);
				}
			} else {
				return renderWithJson($result, 'Address details could not be added. Please, try again.', $validationErrorFields, 1);
			}
		} catch (Exception $e) {
			return renderWithJson($result, 'Address details could not be added. Please, try again.'.$e->getMessage(), '', 1);
		}
	}
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->PUT('/api/v1/user_address/{id}', function ($request, $response, $args) {
    global $authUser;
	$args = $request->getParsedBody();
	$userAddress = Models\UserAddress::find($request->getAttribute('id'));
	$userAddress->fill($args);
	$result = array();
	try {
		$validationErrorFields = $userAddress->validate($args);
		if (empty($validationErrorFields)) {
			if ($args['is_default'] && $args['is_default'] == 1) {
				Models\UserAddress::where('user_id', $authUser->id)->update(array(
					'is_default' => 0
				));
			}
			Models\UserAddress::where('user_id', $authUser->id)->where('id', $request->getAttribute('id'))->update($args);
			return renderWithJson($result, 'Address details updated successfully','', 0);
		} else {
			return renderWithJson($result, 'Address details could not be updated. Please, try again.', $validationErrorFields, 1);
		}
	} catch (Exception $e) {
		return renderWithJson($result, 'Address details could not be updated. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->DELETE('/api/v1/user_address/{id}', function ($request, $response, $args) {
    global $authUser;
	$args = array();
	$args['is_active'] = 0;
	try {
		$count = Models\UserAddress::where('user_id', $authUser->id)->where('is_active', 1)->count();
		if ($count != 1) {
			Models\UserAddress::where('user_id', $authUser->id)->where('id', $request->getAttribute('id'))->update($args);
			$update = array();
			$update['is_default'] = 1;
			$userAdd = Models\UserAddress::where('user_id', $authUser->id)->where('is_active', 1)->first();
			if ($userAdd && !empty($userAdd)) {
				Models\UserAddress::where('id', $userAdd->id)->update($args);
			}
		} else {
			return renderWithJson(array(), 'Default address details could not be deleted','', 1);
		}
		return renderWithJson(array(), 'Address details delete successfully','', 0);
	} catch (Exception $e) {
		return renderWithJson($result, 'Address details could not be delete. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->GET('/api/v1/products', function ($request, $response, $args) {
    global $authUser;
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
		$count = PAGE_LIMIT;
		if (!empty($queryParams['limit'])) {
			$count = $queryParams['limit'];
		}
		$queryParams['is_active'] = true;
		if (!empty($queryParams['filter_by']) && $queryParams['filter_by'] == 'me') {
			$queryParams['user_id'] = $authUser->id;
			$products = Models\Product::with('details_me', 'colors')->Filter($queryParams)->paginate($count)->toArray();
			$products = json_decode(str_replace('details_me', 'details' , str_replace('amount_detail_me', 'amount_detail' , json_encode($products))), true);
		} else {
			if (!empty($authUser['id'])) {
				$queryParams['contest_user_id'] = $authUser->id;
			}
			$products = Models\Product::with('user', 'details', 'colors')->Filter($queryParams)->paginate($count)->toArray();
		}
		$data = $products['data'];
		unset($products['data']);
		$cart_count = 0;
		if (!empty($authUser['id'])) {
			$cart_count = Models\Cart::where('is_purchase', false)->where('user_id', $authUser['id'])->count();
		}
		$results = array(
            'data' => $data,
			'cart_count' => $cart_count,
            '_metadata' => $products
        );
		return renderWithJson($results, 'Success', '', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $e->getMessage(), $fields = '', $isError = 1);
    }
});
$app->GET('/api/v1/product/{id}', function ($request, $response, $args) {
	global $authUser;
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
		$product = Models\Product::with('user', 'details', 'colors')->where('id', $request->getAttribute('id'))->get()->toArray();
        if (!empty($product)) {
            $result['data'] = $product[0];
			$cart_count = 0;
			if (!empty($authUser['id'])) {
				$cart_count = Models\Cart::where('is_purchase', false)->where('user_id', $authUser['id'])->count();
			}
			$result['cart_count'] = $cart_count;
            return renderWithJson($result, 'Success','', 0);
        } else {
            return renderWithJson(array(), 'No record found', '', 1);
        }
    } catch (Exception $e) {
        return renderWithJson(array(), $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->POST('/api/v1/product', function ($request, $response, $args) {
    global $authUser;
    $result = array();
    $args = $request->getParsedBody();
    $product = new Models\Product($args);
    try {
        $validationErrorFields = $product->validate($args);
        if (empty($validationErrorFields)) {
            $product->is_active = 1;
            $product->user_id = $authUser->id;
            if ($product->save()) {
				$productId = $product->id;
				if ($productId) {
					if (!empty($args['product_details'])) {
						foreach($args['product_details'] as $product_detail) {
							$productColor = new Models\ProductColor;
							$productColor->product_id = $productId;
							$productColor->color = $product_detail['color'];
							$productDetail->is_active = true;
							$productColor->save();
							$productColorId = $productColor->id;
							$productDetail = new Models\ProductDetail;
							$productDetail->product_id = $productId;
							$productDetail->product_color_id = $productColorId;
							$productDetail->is_active = true;
							$productDetail->save();
							$product_detail_id = $productDetail->id;
							foreach($product_detail['images'] as $image) {
								saveImage('Product', $image, $product_detail_id, false, $authUser->id);
							}
							foreach($product_detail['sizes'] as $size) {
								$productSize = new Models\ProductSize;
								$productSize->product_detail_id = $product_detail_id;
								$productSize->size_id = $size;
								$productSize->quantity = $product_detail['quantity'];
								$productSize->price = $product_detail['price'];
								if (isset($product_detail['discount_percentage']) && $product_detail['discount_percentage'] != '') {
									$productSize->discount_percentage = $product_detail['discount_percentage'];
									$productSize->coupon_code = ($product_detail['coupon_code'] != "") ? $product_detail['coupon_code'] : null;
								}
								$productSize->is_active = true;
								$productSize->save();								
							}							
						}
					}
					$product = Models\Product::with('user', 'details', 'colors')->where('id', $productId)->get()->toArray();
					$result['data'] = $product[0];
					return renderWithJson($result, 'Product successfully created','', 0);
				}
            }
			return renderWithJson($result, 'Product could not be added. Please, try again.', '', 1);
        } else {
            return renderWithJson($result, 'Product could not be added. Please, try again.', $validationErrorFields, 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Product could not be added. Please, try again.'.$e->getMessage(), '', 1);
    }
})->add(new ACL('canAdmin canContestantUser canCompanyUser'));
$app->PUT('/api/v1/product/{id}', function ($request, $response, $args) {
    global $authUser;
	$args = $request->getParsedBody();
	$product = Models\Product::find($request->getAttribute('id'));
	
	if ($authUser->id != $product->user_id) {
		return renderWithJson(array(), 'Invalid Request', '', 1);
	}
	if (isset($args['is_active']) && $args['is_active'] != '') {
		Models\Product::where('id', $request->getAttribute('id'))->update(array(
						'is_active' => $args['is_active']
					));
	}
	$productDetails = Models\ProductDetail::where('product_id', $request->getAttribute('id'))->get()->toArray();
	if (!empty($productDetails)) {
		foreach($productDetails as $productDetail) {
				Models\ProductSize::where('product_detail_id', $productDetail['id'])->update(array(
						'quantity' => $args['quantity']
					));
		}
	}
	return renderWithJson(array(), 'Product successfully updated','', 0);
})->add(new ACL('canAdmin canContestantUser canCompanyUser'));
$app->DELETE('/api/v1/product/{id}', function ($request, $response, $args) {
    global $authUser;
	$args = array();
	$args['is_active'] = false;
	$product = Models\Product::find($request->getAttribute('id'));
	if ($authUser->id != $product->user_id) {
		return renderWithJson(array(), 'Invalid Request', '', 1);
	}
	$product->fill($args);
	$result = array();
	try {
		$product->save();
		return renderWithJson(array(), 'Product delete successfully','', 0);
	} catch (Exception $e) {
		return renderWithJson($result, 'Product could not be delete. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canContestantUser canCompanyUser'));
$app->POST('/api/v1/coupon', function ($request, $response, $args) {
	global $authUser;
    $args = $request->getParsedBody();
    $results = array();
    try {
		if (!empty($args['product_detail_id']) && $args['product_detail_id'] != '' && !empty($args['coupon_code']) && $args['coupon_code'] != '') {
			$couponSize = Models\ProductSize::where('coupon_code', $args['coupon_code'])->where('product_detail_id', $args['product_detail_id'])->first();
			if (!empty($couponSize)) {
				$results = array(
					'valid' => true
				);
				return renderWithJson($results, 'Valid Code','', 0);
			} else {
				$results = array(
					'valid' => false
				);
				return renderWithJson($results, 'Invalid Code','', 0);
			}			
		}
		return renderWithJson($results, 'Invalid Request','', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->GET('/api/v1/catagories', function ($request, $response, $args) {
    global $authUser;
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
		$queryParams['sort'] = 'name';
		$queryParams['sortby'] = 'asc';		
		if (!empty($queryParams['type']) && $queryParams['type'] == 'user') {
			$category_id = Models\UserCategory::where('user_id', $authUser->id)->get()->toArray();
			$category_ids = array_column($category_id, 'category_id');
			$categories = Models\Category::where('is_active', true)->whereIn('id', $category_ids)->orderBy('name', 'asc')->get()->toArray();
		} else {
			$categories = Models\Category::where('is_active', true)->orderBy('name', 'asc')->get()->toArray();
		}
		$results = array(
            'data' => $categories
        );
		return renderWithJson($results, 'Categories Successfully fetched','', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
});
$app->POST('/api/v1/user_catagory', function ($request, $response, $args) {
    global $authUser;
    $result = array();
    $args = $request->getParsedBody();
    $product = new Models\Product($args);
    try {
        $validationErrorFields = $product->validate($args);
        if (empty($validationErrorFields)) {
            $product->is_active = 1;
            $product->user_id = $authUser->id;
            if ($product->save()) {
				if ($product->id) {
					if (!empty($args['image'])) {
						saveImage('Product', $args['image'], $product->id);
					}
					$result['data'] = $product->toArray();
					return renderWithJson($result, 'Success','', 0);
				}
            } else {
				return renderWithJson($result, 'Product could not be added. Please, try again.', '', 1);
			}
        } else {
            return renderWithJson($result, 'Product could not be added. Please, try again.', $validationErrorFields, 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Product could not be added. Please, try again.'.$e->getMessage(), '', 1);
    }
})->add(new ACL('canAdmin'));
$app->GET('/api/v1/cart', function ($request, $response, $args) {
    global $authUser;
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
		$enabledIncludes = array(
                    'detail',
					'size',
					'coupon'
                );
		$is_purchase = false;
		if (isset($queryParams['pay_key']) && $queryParams['pay_key'] != '') {
			$enabledIncludes = array(
                    'user',
					'detail',
					'size',
					'coupon'
                );
			$carts = Models\Cart::with($enabledIncludes)->where('user_id', $authUser->id)->where('pay_key', $queryParams['pay_key'])->get()->toArray();
		} else if (isset($queryParams['is_purchase']) && $queryParams['is_purchase'] == 'true') {
			$is_purchase = true;			
			$carts = Models\Cart::with($enabledIncludes)->where('user_id', $authUser->id)->where('is_purchase', $is_purchase)->get()->toArray();
		} else {
			$carts = Models\Cart::with($enabledIncludes)->where('user_id', $authUser->id)->where('is_purchase', $is_purchase)->get()->toArray();
		}
		$total_amount = 0;
		$cartFormatted = array();
		if (!empty($carts)) {
			foreach ($carts as $cart) {
				if (!empty($cart['coupon'])) {
					$discountPrice = numberFormat($cart['detail']['amount_detail']['price']-($cart['detail']['amount_detail']['price']*($cart['coupon']['discount_percentage']/100)));
					$cart['detail']['amount_detail']['discount_price'] = $discountPrice * $cart['quantity'];
					if (!isset($queryParams['is_web'])) {
						$cart['detail']['amount_detail']['price'] = ($cart['detail']['amount_detail']['price'] * $cart['quantity']);
					}
					$total_amount = $total_amount + $cart['detail']['amount_detail']['discount_price'];
				} else {
					$cart['detail']['amount_detail']['discount_price'] = 0;
					$total_amount = $total_amount + ($cart['detail']['amount_detail']['price']*$cart['quantity']);
				}				
				$cartFormatted[] = $cart;
			}
		}
		$total_amount = ($total_amount != 0) ? number_format((float)$total_amount, 2, '.', '') : 0;
        $results = array(
            'data' => $cartFormatted,
			'total_amount' => (float)$total_amount
        );
		return renderWithJson($results, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson(array(), $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->PUT('/api/v1/cart', function ($request, $response, $args) {
    global $authUser;
	$result = array();
	$queryParams = $request->getQueryParams();
    $args = $request->getParsedBody();
	$coupon_id = null;
	try {
		if (!empty($args)) {
			if (!isset($args['product_detail_id']) || $args['product_detail_id'] == '' || !isset($args['quantity']) || $args['quantity'] == '') {
				return renderWithJson(array(), 'Invalid request','', 1);
			}
			if (!empty($args['coupon_code'])) {
				$couponSize = Models\ProductSize::where('coupon_code', $args['coupon_code'])->where('product_detail_id', $args['product_detail_id'])->first();
				if (!empty($couponSize)) {
					$coupon_id = $args['product_detail_id'];
				} else {
					return renderWithJson(array(), 'Please enter a valid coupon code', '', 1);
				}
			}
			$enabledIncludes = array(
						'detail',
						'size'
					);
			$carts = Models\Cart::with($enabledIncludes)->where('user_id', $authUser->id)->where('is_purchase', false)->get()->toArray();
			$total_amount = 0;
			$userList = array();
			$existingCart = '';
			$i = 0;
			$findIndex = 0;
			if (!empty($carts)) {
				foreach ($carts as $cart) {
					if ($cart['product_detail_id'] == $args['product_detail_id'] && $cart['product_size_id'] == $args['product_size_id']) {
						$existingCart = $cart;
						$findIndex = $i;
					} else {
						$total_amount = $total_amount + $cart['detail']['amount_detail']['price'];						
					}
					$userList[$cart['detail']['product']['user']['company_id']] = $cart['detail']['product']['user']['company_id'];
					$userList[$cart['detail']['product']['user']['id']] = $cart['detail']['product']['user']['id'];
					 $i++;
				}
			}
			if ($total_amount > CART_MAX_AMOUNT) {
				return renderWithJson(array(), 'You have reached the maximum total amount of cart checkout of '.CURRENCY_SYMBOL.CART_MAX_AMOUNT, '', 1);
			}
			$productDetail = Models\ProductDetail::with(array('product_detail_cart', 'amount_detail'))->where('id', $args['product_detail_id'])->first();
			if (!empty($productDetail)) {
				$productDetail = $productDetail->toArray();
			} else {
				return renderWithJson(array(), 'Invalid request','', 1);
			}
			if ($productDetail['amount_detail']['quantity'] == 0) {
				return renderWithJson($result, 'Product out of stock.', '', 1);
			} else if ($args['quantity'] > $productDetail['amount_detail']['quantity']) {
				return renderWithJson($result, 'Quantity exceed than the available quantity', '', 1);
			}
			$priceDetail = ($coupon_id != '' && $productDetail['amount_detail']['discount_percentage'] != '') ? numberFormat($productDetail['amount_detail']['price']-($productDetail['amount_detail']['price']*($productDetail['amount_detail']['discount_percentage']/100))) : $productDetail['amount_detail']['price'];
			if ($existingCart != '') {
				$total_amount = $total_amount + ($args['quantity'] * $priceDetail);
				if ($total_amount > CART_MAX_AMOUNT) {
					return renderWithJson(array(), 'You have reached the maximum total amount of cart checkout of '.CURRENCY_SYMBOL.CART_MAX_AMOUNT, '', 1);
				}
				if ($total_amount > CART_MAX_AMOUNT) {
					return renderWithJson(array(), 'You have reached the maximum total amount of cart checkout of '.CURRENCY_SYMBOL.CART_MAX_AMOUNT, '', 1);
				}
				if ($args['quantity'] > $productDetail['amount_detail']['quantity']) {
					return renderWithJson(array(), 'Product out of stock.', '', 1);
				}
				if ($coupon_id != null) {
					$carts[$findIndex]['coupon_id'] = $coupon_id;
					$carts[$findIndex]['coupon']['coupon_code'] = $args['coupon_code'];
				}
				Models\Cart::where('user_id', $authUser->id)->where('product_detail_id', $args['product_detail_id'])->where('product_size_id', $args['product_size_id'])->where('is_purchase', false)->update(array(
						'quantity' => $args['quantity'],
						'coupon_id' => $coupon_id
					));
				$carts[$findIndex]['quantity'] = $args['quantity'];
				$isUpdate = true;	
			} else {
				$total_amount = $total_amount + ($args['quantity'] * $priceDetail);
				if ($total_amount > CART_MAX_AMOUNT) {
					return renderWithJson(array(), 'You have reached the maximum total amount of cart checkout of '.CURRENCY_SYMBOL.CART_MAX_AMOUNT, '', 1);
				}
				if ($total_amount > CART_MAX_AMOUNT) {
					return renderWithJson(array(), 'You have reached the maximum total amount of cart checkout of '.CURRENCY_SYMBOL.CART_MAX_AMOUNT, '', 1);
				}
				$userList[$productDetail['product_detail_cart']['product_user']['id']] = $productDetail['product_detail_cart']['product_user']['id'];
				$userList[$productDetail['product_detail_cart']['product_user']['company_id']] = $productDetail['product_detail_cart']['product_user']['company_id'];
				if (!empty($userList) && count($userList) > 5) {
					return renderWithJson(array(), 'You have reached the maximum products to checkout', '', 1);
				}
				if ($args['quantity'] > $productDetail['amount_detail']['quantity']) {
					return renderWithJson(array(), 'Product out of stock.', '', 1);
				}
				$cart = new Models\Cart;
				$cart->is_active = 1;
				$cart->user_id = $authUser->id;
				$cart->contestant_id = $productDetail['product_detail_cart']['product_user']['id'];
				$cart->company_id = $productDetail['product_detail_cart']['product_user']['company_id'];
				$cart->product_detail_id = $args['product_detail_id'];
				$cart->quantity = $args['quantity'];
				$cart->product_size_id = $args['product_size_id'];
				if ($coupon_id != null) {
					$cart->coupon_id = $coupon_id;
				}
				$cart->save();
				if (empty($carts)) {
					$carts[] = $cart;
				}
			}
			if (isset($queryParams['product_id'])) {
				$products = Models\Product::with('cart', 'user', 'details', 'colors')->where('id', $queryParams['product_id'])->first()->toArray();
				$results = array(
					'data' => $products
				);
				return renderWithJson($results, 'Success','', 0);
			} else {
				$results = array(
					'data' => $carts,
					'total_amount' => $total_amount
				);
				$msg = ($isUpdate == true) ? 'Cart updated successfully' : 'Cart added successfully';
			}
			return renderWithJson($results, $msg,'', 0);
		}
		return renderWithJson(array(), 'Invalid request','', 1);
    } catch (Exception $e) {
        return renderWithJson(array(), 'Cart could not be added. Please, try again.', $e->getMessage(), 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->DELETE('/api/v1/cart/{id}', function ($request, $response, $args) {
    global $authUser;
	try {
		Models\Cart::where('id', $request->getAttribute('id'))->where('user_id', $authUser->id)->delete();
		return renderWithJson(array(), 'Your item has been removed from cart successfully','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'Cart could not be delete. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->GET('/api/v1/offline/cart', function ($request, $response, $args) {
    $ipAddress = getClientRequestIP();
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
		$enabledIncludes = array(
                    'detail',
					'size',
					'coupon'
                );
		$carts = Models\OfflineCart::with($enabledIncludes)->where('ipaddress', $ipAddress)->where('is_purchase', false)->get()->toArray();
		$total_amount = 0;
		$cartFormatted = array();
		if (!empty($carts)) {
			foreach ($carts as $cart) {
				if (!empty($cart['coupon'])) {
					$discountPrice = numberFormat($cart['detail']['amount_detail']['price']-($cart['detail']['amount_detail']['price']*($cart['coupon']['discount_percentage']/100)));
					$cart['detail']['amount_detail']['discount_price'] = $discountPrice * $cart['quantity'];
					if (!isset($queryParams['is_web'])) {
						$cart['detail']['amount_detail']['price'] = ($cart['detail']['amount_detail']['price'] * $cart['quantity']);
					}
					$total_amount = $total_amount + $cart['detail']['amount_detail']['discount_price'];
				} else {
					$cart['detail']['amount_detail']['discount_price'] = 0;
					$total_amount = $total_amount + ($cart['detail']['amount_detail']['price']*$cart['quantity']);
				}				
				$cartFormatted[] = $cart;
			}
		}
		$total_amount = ($total_amount != 0) ? number_format((float)$total_amount, 2, '.', '') : 0;
        $results = array(
            'data' => $cartFormatted,
			'total_amount' => (float)$total_amount
        );
		return renderWithJson($results, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson(array(), $message = 'No record found', $fields = '', $isError = 1);
    }
});
$app->PUT('/api/v1/offline/cart', function ($request, $response, $args) {
    $ipAddress = getClientRequestIP();
	$result = array();
	$queryParams = $request->getQueryParams();
    $args = $request->getParsedBody();
	$coupon_id = null;
	try {
		if (!empty($args)) {
			if (!isset($args['product_detail_id']) || $args['product_detail_id'] == '' || !isset($args['quantity']) || $args['quantity'] == '') {
				return renderWithJson(array(), 'Invalid request','', 1);
			}
			if (!empty($args['coupon_code'])) {
				$couponSize = Models\ProductSize::where('coupon_code', $args['coupon_code'])->where('product_detail_id', $args['product_detail_id'])->first();
				if (!empty($couponSize)) {
					$coupon_id = $args['product_detail_id'];
				} else {
					return renderWithJson(array(), 'Please enter a valid coupon code', '', 1);
				}
			}
			$enabledIncludes = array(
						'detail',
						'size'
					);
			$carts = Models\OfflineCart::with($enabledIncludes)->where('ipaddress', $ipAddress)->get()->toArray();
			$total_amount = 0;
			$userList = array();
			$existingCart = '';
			$i = 0;
			$findIndex = 0;
			if (!empty($carts)) {
				foreach ($carts as $cart) {
					if ($cart['product_detail_id'] == $args['product_detail_id'] && $cart['product_size_id'] == $args['product_size_id']) {
						$existingCart = $cart;
						$findIndex = $i;
					} else {
						$total_amount = $total_amount + $cart['detail']['amount_detail']['price'];						
					}
					$userList[$cart['detail']['product']['user']['company_id']] = $cart['detail']['product']['user']['company_id'];
					$userList[$cart['detail']['product']['user']['id']] = $cart['detail']['product']['user']['id'];
					 $i++;
				}
			}
			if ($total_amount > CART_MAX_AMOUNT) {
				return renderWithJson(array(), 'You have reached the maximum total amount of cart checkout of '.CURRENCY_SYMBOL.CART_MAX_AMOUNT, '', 1);
			}
			$productDetail = Models\ProductDetail::with(array('product_detail_cart', 'amount_detail'))->where('id', $args['product_detail_id'])->first();
			if (!empty($productDetail)) {
				$productDetail = $productDetail->toArray();
			} else {
				return renderWithJson(array(), 'Invalid request','', 1);
			}
			if ($productDetail['amount_detail']['quantity'] == 0) {
				return renderWithJson($result, 'Product out of stock.', '', 1);
			} else if ($args['quantity'] > $productDetail['amount_detail']['quantity']) {
				return renderWithJson($result, 'Quantity exceed than the available quantity', '', 1);
			}
			$priceDetail = ($coupon_id != '' && $productDetail['amount_detail']['discount_percentage'] != '') ? numberFormat($productDetail['amount_detail']['price']-($productDetail['amount_detail']['price']*($productDetail['amount_detail']['discount_percentage']/100))) : $productDetail['amount_detail']['price'];
			if ($existingCart != '') {
				$total_amount = $total_amount + ($args['quantity'] * $priceDetail);
				if ($total_amount > CART_MAX_AMOUNT) {
					return renderWithJson(array(), 'You have reached the maximum total amount of cart checkout of '.CURRENCY_SYMBOL.CART_MAX_AMOUNT, '', 1);
				}
				if ($total_amount > CART_MAX_AMOUNT) {
					return renderWithJson(array(), 'You have reached the maximum total amount of cart checkout of '.CURRENCY_SYMBOL.CART_MAX_AMOUNT, '', 1);
				}
				if ($args['quantity'] > $productDetail['amount_detail']['quantity']) {
					return renderWithJson(array(), 'Product out of stock.', '', 1);
				}
				if ($coupon_id != null) {
					$carts[$findIndex]['coupon_id'] = $coupon_id;
					$carts[$findIndex]['coupon']['coupon_code'] = $args['coupon_code'];
				}
				Models\OfflineCart::where('ipaddress', $ipAddress)->where('product_detail_id', $args['product_detail_id'])->where('product_size_id', $args['product_size_id'])->where('is_purchase', false)->update(array(
						'quantity' => $args['quantity'],
						'coupon_id' => $coupon_id
					));
				$carts[$findIndex]['quantity'] = $args['quantity'];
				$isUpdate = true;	
			} else {
				$total_amount = $total_amount + ($args['quantity'] * $priceDetail);
				if ($total_amount > CART_MAX_AMOUNT) {
					return renderWithJson(array(), 'You have reached the maximum total amount of cart checkout of '.CURRENCY_SYMBOL.CART_MAX_AMOUNT, '', 1);
				}
				if ($total_amount > CART_MAX_AMOUNT) {
					return renderWithJson(array(), 'You have reached the maximum total amount of cart checkout of '.CURRENCY_SYMBOL.CART_MAX_AMOUNT, '', 1);
				}
				$userList[$productDetail['product_detail_cart']['product_user']['id']] = $productDetail['product_detail_cart']['product_user']['id'];
				$userList[$productDetail['product_detail_cart']['product_user']['company_id']] = $productDetail['product_detail_cart']['product_user']['company_id'];
				if (!empty($userList) && count($userList) > 5) {
					return renderWithJson(array(), 'You have reached the maximum products to checkout', '', 1);
				}
				if ($args['quantity'] > $productDetail['amount_detail']['quantity']) {
					return renderWithJson(array(), 'Product out of stock.', '', 1);
				}
				$cart = new Models\OfflineCart;
				$cart->is_active = 1;
				$cart->ipaddress = $ipAddress;
				$cart->contestant_id = $productDetail['product_detail_cart']['product_user']['id'];
				$cart->company_id = $productDetail['product_detail_cart']['product_user']['company_id'];
				$cart->product_detail_id = $args['product_detail_id'];
				$cart->quantity = $args['quantity'];
				$cart->product_size_id = $args['product_size_id'];
				if ($coupon_id != null) {
					$cart->coupon_id = $coupon_id;
				}
				$cart->save();
				if (empty($carts)) {
					$carts[] = $cart;
				}
			}
			if (isset($queryParams['product_id'])) {
				$products = Models\Product::with('cart', 'user', 'details', 'colors')->where('id', $queryParams['product_id'])->first()->toArray();
				$results = array(
					'data' => $products
				);
				return renderWithJson($results, 'Success','', 0);
			} else {
				$results = array(
					'data' => $carts,
					'total_amount' => $total_amount
				);
				$msg = ($isUpdate == true) ? 'Cart updated successfully' : 'Cart added successfully';
			}
			return renderWithJson($results, $msg,'', 0);
		}
		return renderWithJson(array(), 'Invalid request','', 1);
    } catch (Exception $e) {
        return renderWithJson(array(), 'Cart could not be added. Please, try again.', $e->getMessage(), 1);
    }
});
$app->DELETE('/api/v1/offline/cart/{id}', function ($request, $response, $args) {
    $ipAddress = getClientRequestIP();
	try {
		Models\OfflineCart::where('id', $request->getAttribute('id'))->where('ipaddress', $ipAddress)->delete();
		return renderWithJson(array(), 'Your item has been removed from cart successfully','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'Cart could not be delete. Please, try again.', $e->getMessage(), 1);
	}
});
$app->GET('/api/v1/vote_packages', function ($request, $response, $args) {
    global $authUser;
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
		$votes = Models\VotePackage::where('is_active', true)->get()->toArray();
		if (!empty($votes)) {
			$response = array(
                            'data' => $votes
                        ); 
			return renderWithJson($response, 'Success','', 0);
		} 
		return renderWithJson(array(), $message = 'No record found', $fields = '', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
});
$app->GET('/api/v1/vote_package/{id}', function ($request, $response, $args) {
    global $authUser;
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
		$votes = Models\VotePackage::where('id', $request->getAttribute('id'))->where('is_active', true)->first()->toArray();
		if (!empty($votes)) {
			$response = $votes; 
			return renderWithJson($response, 'Success','', 0);
		} 
		return renderWithJson(array(), $message = 'No record found', $fields = '', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
});
$app->GET('/api/v1/contest', function ($request, $response, $args) {
    global $authUser;
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
		$queryParams = $request->getQueryParams();
		if (!empty($queryParams) && $queryParams['class'] == 'instants') {
			$contests = Models\Contest::where('is_active', true)->where('type_id', 2)->get()->toArray();
		} else {
			$contests = Models\Contest::where('is_active', true)->get()->toArray();
		}
		
		if (!empty($contests)) {
			$response = array(
                            'data' => $contests,
							'left_time' => (!empty($contests) && !empty($contests[0])) ? strtotime($contests[0]['end_date']): 0
                        ); 
			return renderWithJson($response, 'Success','', 0);
		} 
		return renderWithJson(array(), $message = 'No record found', $fields = '', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
});
$app->GET('/api/v1/user_contests/{contest_id}', function ($request, $response, $args) {
	global $authUser;
	if ($request->getAttribute('contest_id') != '') {
		$queryParams = $request->getQueryParams();
		$results = array();
		try {
			$enabledIncludes = array(
						'attachment',
						'user'
					);
			$contests = Models\UserContest::where('contest_id', $request->getAttribute('contest_id'))->with($enabledIncludes)->get()->toArray();
			if (!empty($contests)) {
				$response = array(
								'data' => $contests
							); 
				return renderWithJson($response, 'Success','', 0);
			} 
			return renderWithJson(array(), $message = 'No record found', $fields = '', 0);
		} catch (Exception $e) {
			return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
		}
	} else {
		return renderWithJson(array(), 'contest_id is required', $fields = '', 0);
	}
});
$app->PUT('/api/v1/vote/{contestant_id}', function ($request, $response, $args) {
    global $authUser;
	try {
		$user_details = Models\User::find($authUser->id);
		if ($user_details->total_votes > 0) {
			$contestant_details = Models\User::find($request->getAttribute('contestant_id'));
			if (!empty($contestant_details)) {
				$user_details->total_votes = $user_details->total_votes - 1;
				$user_details->save();
				$contestant_details->votes = $contestant_details->votes + 1;
				$contestant_details->save();
				return renderWithJson(array(), 'Vote added successfully','', 0);
			} else {
				return renderWithJson(array(), 'User not found','', 1);
			}
		}
		return renderWithJson(array(), 'Purchase Vote','', 1);
	} catch (Exception $e) {
		return renderWithJson(array(), 'Vote could not be add. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->GET('/api/v1/subscription', function ($request, $response, $args) {
    global $authUser;
    $queryParams = $request->getQueryParams();
    try {
		$subscription = Models\Subscription::where('is_active', true)->get()->toArray();
		if (!empty($subscription)) {
			$response = array(
                            'data' => $subscription
                        ); 
			return renderWithJson($response, 'Success','', 0);
		} 
		return renderWithJson(array(), $message = 'No record found', $fields = '', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->GET('/api/v1/purchase/contest/{packageId}', function ($request, $response, $args) {
    global $authUser;
	global $_server_domain_url;
	$queryParams = $request->getQueryParams();
	if (!empty($queryParams['username']) && $queryParams['username'] != '') {
		$contestantInfo = Models\User::where('username', $queryParams['username'])->first();
		$companyInfo = Models\User::where('id', $contestantInfo->company_id)->first();
		$user_model = new Models\User;
        $companyInfo->makeVisible($user_model->hidden);
		$vote_package = Models\VotePackage::where('id', $request->getAttribute('packageId'))->first();
		if (!empty($vote_package)) {
			$paymentGateway = getPaymentDetails($queryParams['payment_gateway_id']);
			$isWeb = '';
			if (isset($queryParams['is_web'])) {
				$isWeb = '&is_web=true';
			}
			if (!empty($paymentGateway)) {
				try {
					$is_sanbox = $paymentGateway['is_test_mode'];
					if ($is_sanbox != 1) {
						$email = $paymentGateway['live_paypal_email'];
						$appId = $paymentGateway['live_application_id'];
					} else {
						$email = $paymentGateway['sanbox_paypal_email'];
						$appId = $paymentGateway['sanbox_application_id'];
					}
					$email = ($is_sanbox != 1) ? $paymentGateway['live_paypal_email'] : $paymentGateway['sanbox_paypal_email'];
					$email = ($is_sanbox != 1) ? $paymentGateway['live_paypal_email'] : $paymentGateway['sanbox_paypal_email'];
					$hash = encrypt_decrypt('encrypt', $authUser->id.'/'.$queryParams['username'].'/'.$queryParams['payment_gateway_id'].'/'.$is_sanbox.'/'.$request->getAttribute('packageId'));
					if ($paymentGateway['name'] == 'PayPal') {
						$amount = $vote_package->price + ((($paymentGateway['paypal_more_ten'] / 100) * $vote_package->price) + $paymentGateway['paypal_more_ten_in_cents']);
						if ($amount < 10) {
							$amount = $vote_package->price + ((($paymentGateway['paypal_less_ten'] / 100) * $vote_package->price) + $paymentGateway['paypal_less_ten_in_cents']);
						}
						$post = array(
							'actionType' => 'PAY',
							'currencyCode' => CURRENCY_CODE,
							'receiverList' => array(
								'receiver'=> array(
									array(
										'email' => $email,
										'amount'=> numberFormat($amount),
										'primary' => true
									),
									array(
										'email' => $contestantInfo->paypal_email,
										'amount'=> numberFormat((SITE_INSTANT_VOTE_EMPLOYER_COMMISSION / 100) * $vote_package->price),
										'primary' => false
									),
									array(
										'email' => $companyInfo->paypal_email,
										'amount'=> numberFormat((SITE_INSTANT_VOTE_COMPANY_COMMISSION / 100) * $vote_package->price),
										'primary' => false
									)
								)
							),
							'memo' => $vote_package->description.' ('.$contestantInfo->first_name.' '.$contestantInfo->last_name.')',
							'clientDetails' => array(
								'applicationId' => $appId,
								'ipAddress' => getClientRequestIP()
							),
							'feesPayer' => 'PRIMARYRECEIVER',
							'requestEnvelope' => array(
								'errorLanguage' => 'en_US'
							),
							'returnUrl' => $_server_domain_url.'/api/v1/purchase/contestant/verify?hash='.$hash.$isWeb,
							'cancelUrl' => $_server_domain_url.'/api/v1/purchase/contestant/verify?hash='.$hash.$isWeb
						);
						$method = 'AdaptivePayments/Pay';
						$response = paypal_pay($post, $method, $paymentGateway);
						if (!empty($response) && $response['ack'] == 'success') {
							$user = Models\User::find($authUser->id);
							$user->instant_vote_pay_key = $response['payKey'];
							$user->instant_vote_to_purchase = $vote_package->vote;
							$user->save();
							$data['payUrl'] = $response['payUrl'];
							$data['verifyUrl'] = $_server_domain_url.'/api/v1/purchase/contestant/verify?success=0&hash='.$hash.$isWeb;
							$data['cancelUrl'] = $_server_domain_url.'/api/v1/purchase/contestant/verify?success=1&hash='.$hash.$isWeb;
							return renderWithJson($data, 'Success','', 0);
						} else {	
							return renderWithJson(array(),'Please check with Administrator', '', 1);
						}
					} else if ($paymentGateway['name'] == 'Stripe') {
						//
					} else if ($paymentGateway['name'] == 'Add Card') {
						$card = Models\Card::where('id', $queryParams['card_id'])->where('user_id', $authUser->id)->get()->toArray();
						if (!empty($card) && $queryParams['ccv']) {
							echo '<pre>';print_r($card);exit;
						} else {
							return renderWithJson(array(), $message = 'Invalid card or ccv', $fields = '', $isError = 1);
						}
					} else {
						return renderWithJson(array(), $message = 'Invalid Payment Gateway', $fields = '', $isError = 1);
					}
				} catch (Exception $e) {
					return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
				}
			} else {
				return renderWithJson(array(), $message = 'Invalid Payment Gateway', $fields = '', $isError = 1);
			}
		} else {
			return renderWithJson(array(), $message = 'Invalid Package is empty', $fields = '', $isError = 1);
		}
	} else {
		return renderWithJson(array(), $message = 'contestant is required', $fields = '', $isError = 1);
	}	
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->GET('/api/v1/purchase/contestant/verify', function ($request, $response, $args) {
	global $_server_domain_url;
	$queryParams = $request->getQueryParams();
	if ($queryParams['hash'] != '') {
		$pay_data = explode('/',encrypt_decrypt('decrypt', $queryParams['hash']));
		if (!empty($pay_data)) {
			$user_id = $pay_data[0];
			$contestant_id = $pay_data[1];
			$payment_gateway_id = $pay_data[2];
			$is_sanbox = $pay_data[3];
			$foreign_id = $pay_data[4];
			$user = Models\User::find($user_id);
			$paymentGateway = getPaymentDetails($payment_gateway_id);
			if (!empty($user) && $user->instant_vote_pay_key != '') {
				$post = array(
						'payKey' => $user->instant_vote_pay_key,
						'requestEnvelope' => array(
							'errorLanguage' => 'en_US'
						)
					);
				$method = 'AdaptivePayments/PaymentDetails';
				sleep(10);
				$response = paypal_pay($post, $method, $paymentGateway);
				if (!empty($response) && $response['ack'] == 'success' && !empty($response['response'])) {
					if (strtolower($response['response']['status']) == 'completed') {
						$vote_package = Models\VotePackage::where('id', $foreign_id)->first();
						$contestantInfo = Models\User::where('username', $contestant_id)->first();
						$contestant_id = $contestantInfo->id;
						$contestant_details = Models\UserContest::where('user_id', $contestantInfo->id)->first();
						$contestant_details->instant_votes = $contestant_details->instant_votes + $vote_package->vote;
						$contestant_details->save();
						$user->instant_vote_pay_key = '';
						$user->save();
						$i = 0;
						foreach($response['response']['paymentInfoList']['paymentInfo'] as $paymentInfo) {
							if ($i == 0) {
								insertTransaction(0, 1, \Constants\TransactionClass::InstantPackage, \Constants\TransactionType::InstantPackage, $payment_gateway_id, $paymentInfo['receiver']['amount'], 0, 0, 0, 0, $foreign_id, $is_sanbox, $user_id, $paymentInfo['transactionStatus'], $paymentInfo['transactionId'], $paymentInfo['senderTransactionId']);
							}
							if ($i == 1) {
								insertTransaction(0, $contestantInfo->company_id, \Constants\TransactionClass::InstantPackage, \Constants\TransactionType::InstantPackage, $payment_gateway_id, $paymentInfo['receiver']['amount'], 0, 0, 0, 0, $foreign_id, $is_sanbox, $user_id, $paymentInfo['transactionStatus'], $paymentInfo['transactionId'], $paymentInfo['senderTransactionId']);
							}
							if ($i == 2) {
								insertTransaction(0, $contestant_id, \Constants\TransactionClass::InstantPackage, \Constants\TransactionType::InstantPackage, $payment_gateway_id, $paymentInfo['receiver']['amount'], 0, 0, 0, 0, $foreign_id, $is_sanbox, $user_id, $paymentInfo['transactionStatus'], $paymentInfo['transactionId'], $paymentInfo['senderTransactionId']);
							}
							$i++;
						}
						insertTransaction($user_id, 0, \Constants\TransactionClass::InstantPackage, \Constants\TransactionType::InstantPackage, $payment_gateway_id, $vote_package->price, 0, 0, 0, 0, $foreign_id, $is_sanbox, $contestant_id ,$response['response']['paymentInfoList']['paymentInfo'][0]['transactionStatus'], $response['response']['paymentInfoList']['paymentInfo'][0]['transactionId'], $response['response']['paymentInfoList']['paymentInfo'][0]['senderTransactionId']);
						if (isset($queryParams['is_web'])) {
							echo '<script>location.replace("/instant_vote_success/'.$contestantInfo->slug.'");</script>';exit;
						} else {
							echo '<script>location.replace("'.$_server_domain_url.'/api/v1/purchase/contestant/verify?success=0");</script>';exit;
						}
					} else if (strtolower($response['response']['status']) == 'created') {
						$data = array(
										'pay_status' => strtolower($response['response']['status'])
									);
						if (isset($queryParams['is_web'])) {
							echo '<script>location.replace("/?pending=0");</script>';exit;
						} else {
							echo '<script>location.replace("'.$_server_domain_url.'/api/v1/purchase/contestant/verify?success=1");</script>';exit;
						}
					} else  {
						$data = array(
										'pay_status' => strtolower($response['response']['status'])
									);
						if (isset($queryParams['is_web'])) {
							echo '<script>location.replace("/?fail=1");</script>';exit;
						} else {
							echo '<script>location.replace("'.$_server_domain_url.'/api/v1/purchase/contestant/verify?success=2");</script>';exit;
						}
					}
				}
			}
		}
	}	
	return renderWithJson(array(),'Please check with Administrator', '', 1);
});
$app->GET('/api/v1/purchase/vote_package/{packageId}', function ($request, $response, $args) {
    global $authUser;
	global $_server_domain_url;
	$queryParams = $request->getQueryParams();
	if (!empty($queryParams['username']) && $queryParams['username'] != '' && $queryParams['category_id'] != '') {
		$contestantInfo = Models\User::with(array('company'))->where('username', $queryParams['username'])->first();
		$companyInfo = Models\User::where('id', $contestantInfo->company_id)->first();
		$user_model = new Models\User;
        $companyInfo->makeVisible($user_model->hidden);
		if (isset($queryParams['is_web'])) {
			$isWeb = '&is_web=true';
		}
		$vote_package = Models\VotePackage::where('id', $request->getAttribute('packageId'))->first();
		if (!empty($vote_package)) {
			$paymentGateway = getPaymentDetails($queryParams['payment_gateway_id']);
			if (!empty($paymentGateway)) {
				try {
					$is_sanbox = $paymentGateway['is_test_mode'];
					if ($is_sanbox != 1) {
						$email = $paymentGateway['live_paypal_email'];
						$appId = $paymentGateway['live_application_id'];
					} else {
						$email = $paymentGateway['sanbox_paypal_email'];
						$appId = $paymentGateway['sanbox_application_id'];
					}
					$hash = encrypt_decrypt('encrypt', $authUser->id.'/'.$queryParams['username'].'/'.$queryParams['category_id'].'/'.$queryParams['payment_gateway_id'].'/'.$is_sanbox.'/'.$request->getAttribute('packageId'));
					if ($paymentGateway['name'] == 'PayPal') {
						$amount = $vote_package->price + ((($paymentGateway['paypal_more_ten'] / 100) * $vote_package->price) + $paymentGateway['paypal_more_ten_in_cents']);
						if ($amount < 10) {
							$amount = $vote_package->price + ((($paymentGateway['paypal_less_ten'] / 100) * $vote_package->price) + $paymentGateway['paypal_less_ten_in_cents']);
						}
						$post = array(
							'actionType' => 'PAY',
							'currencyCode' => CURRENCY_CODE,
							'receiverList' => array(
								'receiver'=> array(
									array(
										'email' => $email,
										'amount'=> numberFormat($amount),
										'primary' => true
									),
									array(
										'email' => $contestantInfo->paypal_email,
										'amount'=> numberFormat((SITE_VOTE_EMPLOYER_COMMISSION / 100) * $vote_package->price),
										'primary' => false
									),
									array(
										'email' => $companyInfo->paypal_email,
										'amount'=> numberFormat((SITE_VOTE_COMPANY_COMMISSION / 100) * $vote_package->price),
										'primary' => false
									)
								)
							),
							'memo' => $vote_package->description.' ('.$contestantInfo->first_name.' '.$contestantInfo->last_name.')',
							'clientDetails' => array(
								'applicationId' => $appId,
								'ipAddress' => getClientRequestIP()
							),
							'feesPayer' => 'PRIMARYRECEIVER',
							'requestEnvelope' => array(
								'errorLanguage' => 'en_US'
							),
							'returnUrl' => $_server_domain_url.'/api/v1/purchase/package/verify?hash='.$hash.$isWeb,
							'cancelUrl' => $_server_domain_url.'/api/v1/purchase/package/verify?hash='.$hash.$isWeb,
						);
						$method = 'AdaptivePayments/Pay';
						$response = paypal_pay($post, $method, $paymentGateway);
						if (!empty($response) && $response['ack'] == 'success') {
							$user = Models\User::find($authUser->id);
							$user->vote_pay_key = $response['payKey'];
							$user->vote_to_purchase = $vote_package->vote;
							$user->save();
							$data['payUrl'] = $response['payUrl'];
							$data['verifyUrl'] = $_server_domain_url.'/api/v1/purchase/package/verify?success=0&hash='.$hash.$isWeb;
							$data['cancelUrl'] = $_server_domain_url.'/api/v1/purchase/package/verify?success=1&hash='.$hash.$isWeb;
							return renderWithJson($data, 'Success','', 0);
						} else {	
							return renderWithJson(array(),'Paypal Server is not available right now please try after some time.', '', 1);
						}
					} else if ($paymentGateway['name'] == 'Stripe') {
						//
					} else if ($paymentGateway['name'] == 'Add Card') {
						$card = Models\Card::where('id', $queryParams['card_id'])->where('user_id', $authUser->id)->get()->toArray();
						if (!empty($card) && $queryParams['ccv']) {
							echo '<pre>';print_r($card);exit;
						} else {
							return renderWithJson(array(), $message = 'Invalid card or ccv', $fields = '', $isError = 1);
						}
					} else {
						return renderWithJson(array(), $message = 'Invalid Payment Gateway', $fields = '', $isError = 1);
					}
				} catch (Exception $e) {
					return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
				}
			} else {
				return renderWithJson(array(), $message = 'Invalid Payment Gateway', $fields = '', $isError = 1);
			}
		} else {
			return renderWithJson(array(), $message = 'Invalid Package is empty', $fields = '', $isError = 1);
		}
	} else {
		return renderWithJson(array(), $message = 'contestant is required', $fields = '', $isError = 1);
	}	
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));	
$app->GET('/api/v1/purchase/package/verify', function ($request, $response, $args) {
	global $_server_domain_url;
	$queryParams = $request->getQueryParams();
	if ($queryParams['hash'] != '') {
		$pay_data = explode('/',encrypt_decrypt('decrypt', $queryParams['hash']));
		if (!empty($pay_data)) {
			$user_id = $pay_data[0];
			$contestant_id = $pay_data[1];
			$category_id = $pay_data[2];
			$payment_gateway_id = $pay_data[3];
			$paymentGateway = getPaymentDetails($payment_gateway_id);
			$is_sanbox = $pay_data[4];
			$foreign_id = $pay_data[5];
			$user = Models\User::find($user_id);
			if (!empty($user) && $user->vote_pay_key != '') {
				$post = array(
						'payKey' => $user->vote_pay_key,
						'requestEnvelope' => array(
							'errorLanguage' => 'en_US'
						)
					);
				$method = 'AdaptivePayments/PaymentDetails';
				sleep(10);
				$response = paypal_pay($post, $method, $paymentGateway);
				if (!empty($response) && $response['ack'] == 'success' && !empty($response['response'])) {
					if (strtolower($response['response']['status']) == 'completed') {
						$vote_package = Models\VotePackage::where('id', $foreign_id)->first();
						$contestantInfo = Models\User::where('username', $contestant_id)->first();
						
						$contestant_id = $contestantInfo->id;
						$contestant_details = Models\User::find($contestant_id);
						$contestant_details->votes = $contestant_details->votes + $vote_package->vote;
						$contestant_details->save();
						$userCategory = Models\UserCategory::where('user_id',$contestant_id)->where('category_id', $category_id)->first();
						Models\UserCategory::where('user_id', $contestant_id)->where('category_id', $category_id)->update(array(
										'votes' => $userCategory->votes + $vote_package->vote
									));
						$i = 0;
						foreach($response['response']['paymentInfoList']['paymentInfo'] as $paymentInfo) {
							if ($i == 0) {
								insertTransaction(0, 1, \Constants\TransactionClass::VotePackage, \Constants\TransactionType::VotePackage, $payment_gateway_id, $paymentInfo['receiver']['amount'], 0, 0, 0, 0, $foreign_id, $is_sanbox, $user_id, $paymentInfo['transactionStatus'], $paymentInfo['transactionId'], $paymentInfo['senderTransactionId']);
							}
							if ($i == 1) {
								insertTransaction(0, $contestantInfo->company_id, \Constants\TransactionClass::VotePackage, \Constants\TransactionType::VotePackage, $payment_gateway_id, $paymentInfo['receiver']['amount'], 0, 0, 0, 0, $foreign_id, $is_sanbox, $user_id, $paymentInfo['transactionStatus'], $paymentInfo['transactionId'], $paymentInfo['senderTransactionId']);
							}
							if ($i == 2) {
								insertTransaction(0, $contestant_id, \Constants\TransactionClass::VotePackage, \Constants\TransactionType::VotePackage, $payment_gateway_id, $paymentInfo['receiver']['amount'], 0, 0, 0, 0, $foreign_id, $is_sanbox, $user_id, $paymentInfo['transactionStatus'], $paymentInfo['transactionId'], $paymentInfo['senderTransactionId']);
							}
							$i++;
						}
						insertTransaction($user_id, 0, \Constants\TransactionClass::VotePackage, \Constants\TransactionType::VotePackage, $payment_gateway_id, $vote_package->price, 0, 0, 0, 0, $foreign_id, $is_sanbox, $contestant_id ,$response['response']['paymentInfoList']['paymentInfo'][0]['transactionStatus'], $response['response']['paymentInfoList']['paymentInfo'][0]['transactionId'], $response['response']['paymentInfoList']['paymentInfo'][0]['senderTransactionId']);
						$user->vote_pay_key = '';
						$user->save();
						if (isset($queryParams['is_web'])) {
							echo '<script>location.replace("/vote_success/'.$contestantInfo->slug.'");</script>';exit;
						} else {
							echo '<script>location.replace("'.$_server_domain_url.'/api/v1/purchase/package/verify?success=0");</script>';exit;
						}
					} else if (strtolower($response['response']['status']) == 'created') {
						$data = array(
										'pay_status' => strtolower($response['response']['status'])
									);
						if (isset($queryParams['is_web'])) {
							echo '<script>location.replace("/?pending=0");</script>';exit;
						} else {
							echo '<script>location.replace("'.$_server_domain_url.'/api/v1/purchase/package/verify?success=1");</script>';exit;
						}
					} else  {
						$data = array(
										'pay_status' => strtolower($response['response']['status'])
									);
						if (isset($queryParams['is_web'])) {
							echo '<script>location.replace("/?fail=0");</script>';exit;
						} else {
							echo '<script>location.replace("'.$_server_domain_url.'/api/v1/purchase/package/verify?success=2");</script>';exit;
						}
					}
				}
			}
		}
	}	
	return renderWithJson(array(),'Please check with Administrator', '', 1);
});
$app->GET('/api/v1/purchase/cart', function ($request, $response, $args) {
    global $authUser;
	global $_server_domain_url;
	$queryParams = $request->getQueryParams();
	if ($queryParams['user_address_id'] == '') {
		return renderWithJson(array(),'Address is required', '', 1);
	}
	$enabledIncludes = array(
				'detail_cart'
			);
			
	$carts = Models\Cart::with($enabledIncludes)->where('user_id', $authUser->id)->where('is_purchase' , false)->get()->toArray();
	if (!empty($carts)) {
		$parentId = $carts[0]['id'];
		$receivers = array();
		foreach ($carts as $cart) {
			if (!empty($cart['coupon_id']) && $cart['coupon_id'] != '') {
				$discountPrice = $cart['detail_cart']['amount_detail']['price']-($cart['detail_cart']['amount_detail']['price']*($cart['detail_cart']['amount_detail']['discount_percentage']/100));
				$discountPriceFinal = $discountPrice * $cart['quantity'];
			} else {
				$discountPriceFinal = ($cart['detail_cart']['amount_detail']['price']*$cart['quantity']);
			}
			if (isset($receivers[$cart['detail_cart']['product_detail_cart']['product_user']['id']])) {
				$receivers[$cart['detail_cart']['product_detail_cart']['product_user']['id']]['amount'] = ($receivers[$cart['detail_cart']['product_detail_cart']['product_user']['id']]['amount'] + ((SITE_PRODUCT_EMPLOYER_COMMISSION / 100) * $discountPriceFinal));
			} else {
				$receivers[$cart['detail_cart']['product_detail_cart']['product_user']['id']]['amount'] = ((SITE_PRODUCT_EMPLOYER_COMMISSION / 100) * $discountPriceFinal);
			}
			if (isset($cart['detail_cart']['product_detail_cart']['product_user']['company_id'])) {
				$receivers[$cart['detail_cart']['product_detail_cart']['product_user']['company_id']]['amount'] = ($receivers[$cart['detail_cart']['product_detail_cart']['product_user']['company_id']]['amount'] + ((SITE_PRODUCT_COMPANY_COMMISSION / 100) * $discountPriceFinal));
			} else {
				$receivers[$cart['detail_cart']['product_detail_cart']['product_user']['company_id']]['amount'] = ((SITE_PRODUCT_COMPANY_COMMISSION / 100) * $discountPriceFinal);
			}			
			$total_amount = $total_amount + $discountPriceFinal;
			Models\Cart::where('user_id', $authUser->id)->where('id', $cart['id'])->update(array(
							'price' => $discountPriceFinal,
							'parent_id' => $parentId
						));
		}
		$users = Models\User::select('id', 'paypal_email')->whereIn('id', array_keys($receivers))->get();
		$user_model = new Models\User;
        $users->makeVisible($user_model->hidden);
		$users = $users->toArray();
		$paymentGateway = getPaymentDetails($queryParams['payment_gateway_id']);
		if (!empty($paymentGateway)) {
			try {
				$isWeb = '';
				if (isset($queryParams['is_web'])) {
					$isWeb = '&is_web=true';
				}
				$is_sanbox = $paymentGateway['is_test_mode'];
				$hash = encrypt_decrypt('encrypt', $authUser->id.'/'.$queryParams['user_address_id'].'/'.$queryParams['payment_gateway_id'].'/'.$is_sanbox);
				if ($paymentGateway['name'] == 'PayPal') {
					$email = ($is_sanbox != 1) ? $paymentGateway['live_paypal_email'] : $paymentGateway['sanbox_paypal_email'];
					foreach ($users as $user) {
						$receivers[$user['id']]['email'] = $user['paypal_email'];
						$receivers[$user['id']]['primary'] = false;
						$receivers[$user['id']]['amount'] = numberFormat($receivers[$user['id']]['amount']);
					}
					$amount = $total_amount + ((($paymentGateway['paypal_more_ten'] / 100) * $total_amount) + $paymentGateway['paypal_more_ten_in_cents']);
					if ($amount < 10) {
						$amount = $total_amount + ((($paymentGateway['paypal_less_ten'] / 100) *$total_amount) + $paymentGateway['paypal_less_ten_in_cents']);
					}
					$receivers[1]['email'] = $email;
					$receivers[1]['primary'] = true;
					$receivers[1]['amount'] = numberFormat($amount);
					$post = array(
						'actionType' => 'PAY',
						'currencyCode' => CURRENCY_CODE,
						'receiverList' => array(
							'receiver'=> array_values($receivers)
						),
						'memo' => 'IMA shop purchase of '.count($carts).' items.',
						'requestEnvelope' => array(
							'errorLanguage' => 'en_US'
						),
						'feesPayer' => 'PRIMARYRECEIVER',
						'returnUrl' => $_server_domain_url.'/api/v1/purchase/cart/verify?hash='.$hash.$isWeb,
						'cancelUrl' => $_server_domain_url.'/api/v1/purchase/cart/verify?hash='.$hash.$isWeb,
					);
					$method = 'AdaptivePayments/Pay';
					$response = paypal_pay($post, $method, $paymentGateway);
					if (!empty($response) && $response['ack'] == 'success') {
						Models\Cart::where('user_id', $authUser->id)->update(array(
							'pay_key' => $response['payKey'],
							'user_address_id' => $queryParams['user_address_id']
						));
						$data['payUrl'] = $response['payUrl'];
						$data['payKey'] = $response['payKey'];
						$data['verifyUrl'] = $_server_domain_url.'/api/v1/purchase/cart/verify?success=0&hash='.$hash.$isWeb;
						$data['cancelUrl'] = $_server_domain_url.'/api/v1/purchase/cart/verify?success=1&hash='.$hash.$isWeb;
						return renderWithJson($data, 'Success','', 0);
					} else {	
						return renderWithJson(array(),'Paypal Server is not available right now please try after some time.', '', 1);
					}
				} else if ($paymentGateway['name'] == 'Stripe') {
					//
				} else if ($paymentGateway['name'] == 'Add Card') {
					$card = Models\Card::where('id', $queryParams['card_id'])->where('user_id', $authUser->id)->get()->toArray();
					if (!empty($card) && $queryParams['ccv']) {
						echo '<pre>';print_r($card);exit;
					} else {
						return renderWithJson(array(), 'Invalid card or ccv', $fields = '', $isError = 1);
					}
				} else {
					return renderWithJson(array(), 'Invalid Payment Gateway', $fields = '', $isError = 1);
				}
			} catch (Exception $e) {
				return renderWithJson($results, 'No record found', $fields = '', $isError = 1);
			}
		} else {
			return renderWithJson(array(), 'Invalid Payment Gateway', $fields = '', $isError = 1);
		}
	} else {
		return renderWithJson(array(), 'Cart is empty', $fields = '', $isError = 1);
	}
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));	
$app->GET('/api/v1/purchase/cart/verify', function ($request, $response, $args) {
	$queryParams = $request->getQueryParams();
	if ($queryParams['hash'] && $queryParams['hash'] != '') {
		$pay_data = explode('/',encrypt_decrypt('decrypt', $queryParams['hash']));
		if (!empty($pay_data[0])) {
			$user_id = $pay_data[0];
			$address_id = $pay_data[1];
			$payment_gateway_id = $pay_data[2];
			$is_sanbox = $pay_data[3];
			$paymentGateway = getPaymentDetails($payment_gateway_id);
			$cart = Models\Cart::where('user_id', $user_id)->where('is_purchase', false)->first();
			if (!empty($cart) && $cart->pay_key != '') {
				$post = array(
						'payKey' => $cart->pay_key,
						'requestEnvelope' => array(
							'errorLanguage' => 'en_US'
						)
					);
				$method = 'AdaptivePayments/PaymentDetails';
				sleep(10);
				$response = paypal_pay($post, $method, $paymentGateway);
				if (!empty($response) && $response['ack'] == 'success' && !empty($response['response'])) {
					$data = array();
					if (strtolower($response['response']['status']) == 'completed') {
						$address_data = Models\UserAddress::where('id', $address_id)->first();
						$data = array(
										'is_purchase' => true,
										'pay_status' => strtolower($response['response']['status']),
										'addressline1' => $address_data->addressline1,
										'addressline2' => $address_data->addressline2,
										'city' => $address_data->city,
										'state' => $address_data->state,
										'country' => $address_data->country,
										'zipcode'=> $address_data->zipcode,
										'invoice_no' => rand(1000,9999),
										'shipping_status'=> 'Initiated',
										'otp'=> generateNumericOTP(4)
									);
						Models\Cart::where('user_id', $user_id)->where('pay_key', $cart->pay_key)->update($data);
						$enabledIncludes = array(
							'detail_cart'
						);
						$carts = Models\Cart::with($enabledIncludes)->where('pay_key', $cart->pay_key)->get()->toArray();
						foreach ($carts as $cart) {
							$foreign_id = $cart['product_detail_id'];
							Models\ProductSize::where('product_detail_id', $cart['product_detail_id'])->update(array(
								'quantity' => ($cart['detail_cart']['amount_detail']['quantity'] - $cart['quantity'])
							));
							if (!empty($cart['coupon_id']) && $cart['coupon_id'] != '') {
								$discountPrice = $cart['detail_cart']['amount_detail']['price']-($cart['detail_cart']['amount_detail']['price']*($cart['detail_cart']['amount_detail']['discount_percentage']/100));
								$discountPriceFinal = $discountPrice * $cart['quantity'];
							} else {
								$discountPriceFinal = ($cart['detail_cart']['amount_detail']['price']*$cart['quantity']);
							}
							$contestPrice = ((SITE_PRODUCT_EMPLOYER_COMMISSION / 100) * $discountPriceFinal);
							$companyPrice = ((SITE_PRODUCT_COMPANY_COMMISSION / 100) * $discountPriceFinal);
							$adminPrice = $discountPriceFinal - ($contestPrice + $companyPrice);
							
							insertTransaction(0, 1, \Constants\TransactionClass::Product, \Constants\TransactionType::Product, $payment_gateway_id, $adminPrice, 0, 0, 0, 0, $foreign_id, $is_sanbox, $user_id, $response['response']['status'], '', '');
							
							insertTransaction(0, $cart['detail_cart']['product_detail_cart']['product_user']['id'], \Constants\TransactionClass::Product, \Constants\TransactionType::Product, $payment_gateway_id, $contestPrice, 0, 0, 0, 0, $foreign_id, $is_sanbox, $user_id, $response['response']['status'], '', '');
							
							insertTransaction(0, $cart['detail_cart']['product_detail_cart']['product_user']['company_id'], \Constants\TransactionClass::Product, \Constants\TransactionType::Product, $payment_gateway_id, $companyPrice, 0, 0, 0, 0, $foreign_id, $is_sanbox, $user_id, $response['response']['status'], '', '');
							
							insertTransaction($user_id, 0, \Constants\TransactionClass::Product, \Constants\TransactionType::Product, $payment_gateway_id, $cart['price'], 0, 0, 0, 0, $foreign_id, $is_sanbox, $cart['detail_cart']['product_detail_cart']['product_user']['id'], $response['response']['status'], '', '');
						}
						if (isset($queryParams['is_web'])) {
							echo '<script>location.replace("/?success=0");</script>';exit;
						}
						return renderWithJson(array(), 'Products added Successfully','', 0);
						
					} else if (strtolower($response['response']['status']) == 'created') {
						$data = array(
										'pay_status' => strtolower($response['response']['status'])
									);
						Models\Cart::where('user_id', $user_id)->where('pay_key', $cart->pay_key)->update($data);
						if (isset($queryParams['is_web'])) {
							echo '<script>location.replace("/?pending=0");</script>';exit;
						}
						return renderWithJson(array(), 'Payment Pending','', 0);
					} else  {
						$data = array(
										'pay_status' => strtolower($response['response']['status'])
									);
						Models\Cart::where('user_id', $user_id)->where('pay_key', $cart->pay_key)->update($data);
						if (isset($queryParams['is_web'])) {
							echo '<script>location.replace("/?fail=0");</script>';exit;
						}
						return renderWithJson(array(), 'Payment Failed','', 0);
					}
				}
			}
			return renderWithJson(array(),'Please check with Administrator', '', 1);
		}
	} else {
		return renderWithJson(array(),'Payment couldn\'t be verified', '', 1);
	}
});
$app->GET('/api/v1/purchase/subscription/{packageId}', function ($request, $response, $args) {
    global $authUser;
	global $_server_domain_url;
	$queryParams = $request->getQueryParams();
	$subscription = Models\Subscription::where('id', $request->getAttribute('packageId'))->first();
    if (!empty($subscription)) {
		$paymentGateway = getPaymentDetails($queryParams['payment_gateway_id']);
		if (!empty($paymentGateway)) {
			try {
				$isWeb = '';
				if (isset($queryParams['is_web'])) {
					$isWeb = '&is_web=true';
				}
				$is_sanbox = $paymentGateway['is_test_mode'];
				$email = ($is_sanbox != 1) ? $paymentGateway['live_paypal_email'] : $paymentGateway['sanbox_paypal_email'];
				$hash = encrypt_decrypt('encrypt', $authUser->id.'/'.$queryParams['payment_gateway_id'].'/'.$is_sanbox.'/'.$request->getAttribute('packageId'));
				$amount = $subscription->price + ((($paymentGateway['paypal_more_ten'] / 100) * $subscription->price) + $paymentGateway['paypal_more_ten_in_cents']);
				if ($amount < 10) {
					$amount = $subscription->price + ((($paymentGateway['paypal_less_ten'] / 100) * $subscription->price) + $paymentGateway['paypal_less_ten_in_cents']);
				}
				if ($paymentGateway['name'] == 'PayPal') {
					$post = array(
						'actionType' => 'PAY',
						'currencyCode' => CURRENCY_CODE,
						'receiverList' => array(
							'receiver'=> array(
								array(
									'email' => $email,
									'amount'=> numberFormat($amount)
								)
							)
						),
						'requestEnvelope' => array(
							'errorLanguage' => 'en_US'
						),
						'returnUrl' => $_server_domain_url.'/api/v1/purchase/subscribe/verify?hash='.$hash.$isWeb,
						'cancelUrl' => $_server_domain_url.'/api/v1/purchase/subscribe/verify?hash='.$hash.$isWeb
					);
					$method = 'AdaptivePayments/Pay';
					$response = paypal_pay($post, $method, $paymentGateway);
					if (!empty($response) && $response['ack'] == 'success') {
						$user = Models\User::find($authUser->id);
						$user->subscription_pay_key = $response['payKey'];
						$user->subscription_id = $request->getAttribute('packageId');
						$user->save();
						$data['payUrl'] = $response['payUrl'];
						$data['verifyUrl'] = $_server_domain_url.'/api/v1/purchase/subscribe/verify?success=0&hash='.$hash.$isWeb;
						$data['cancelUrl'] = $_server_domain_url.'/api/v1/purchase/subscribe/verify?success=1&hash='.$hash.$isWeb;
						return renderWithJson($data, 'Success','', 0);
					} else {	
						return renderWithJson(array(),'Paypal Server is not available right now please try after some time.', '', 1);
					}
				} else if ($paymentGateway['name'] == 'Stripe') {
					//
				} else if ($paymentGateway['name'] == 'Add Card') {
					$card = Models\Card::where('id', $queryParams['card_id'])->where('user_id', $authUser->id)->get()->toArray();
					if (!empty($card) && $queryParams['ccv']) {
						echo '<pre>';print_r($card);exit;
					} else {
						return renderWithJson(array(), $message = 'Invalid card or ccv', $fields = '', $isError = 1);
					}
				} else {
					return renderWithJson(array(), $message = 'Invalid Payment Gateway', $fields = '', $isError = 1);
				}
			} catch (Exception $e) {
				return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
			}
		} else {
			return renderWithJson(array(), $message = 'Invalid Payment Gateway', $fields = '', $isError = 1);
		}
	} else {
		return renderWithJson(array(), $message = 'Invalid Package', $fields = '', $isError = 1);
	}
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->GET('/api/v1/purchase/subscribe/verify', function ($request, $response, $args) {
	global $_server_domain_url;
	$queryParams = $request->getQueryParams();
	if ($queryParams['hash'] != '') {
		$pay_data = explode('/',encrypt_decrypt('decrypt', $queryParams['hash']));
		$user_id = $pay_data[0];
		$payment_gateway_id = $pay_data[1];
		$paymentGateway = getPaymentDetails($payment_gateway_id);
		$is_sanbox = $pay_data[2];
		$foreign_id = $pay_data[3];
		$user = Models\User::find($user_id);
		if (!empty($user) && $user->subscription_pay_key != '') {
			$post = array(
						'payKey' => $user->subscription_pay_key,
						'requestEnvelope' => array(
							'errorLanguage' => 'en_US'
						)
					);
			$method = 'AdaptivePayments/PaymentDetails';
			sleep(10);
			$response = paypal_pay($post, $method, $paymentGateway);
			if (!empty($response) && $response['ack'] == 'success' && !empty($response['response'])) {
				if (strtolower($response['response']['status']) == 'completed') {
					$subscription = Models\Subscription::where('id', $user->subscription_id)->first();
					Models\User::where('id', $user_id)->update(array(
							'subscription_end_date' => date('Y-m-d', strtotime('+'.$subscription->days.' days')),
							'subscription_pay_key' => '',
							'subscription_id' => null
					));
					insertTransaction($user_id, 1, \Constants\TransactionClass::SubscriptionPackage, \Constants\TransactionType::SubscriptionPackage, $payment_gateway_id, $response['response']['paymentInfoList']['paymentInfo'][0]['receiver']['amount'], 0, 0, 0, 0, $foreign_id, $is_sanbox, 0 ,$response['response']['paymentInfoList']['paymentInfo'][0]['transactionStatus'], $response['response']['paymentInfoList']['paymentInfo'][0]['transactionId'], $response['response']['paymentInfoList']['paymentInfo'][0]['senderTransactionId']);
					if (isset($queryParams['is_web'])) {
						echo '<script>location.replace("/?success=0");</script>';exit;
					} else {
						echo '<script>location.replace("'.$_server_domain_url.'/api/v1/purchase/subscribe/verify?success=0");</script>';exit;
					}
				} else if (strtolower($response['response']['status']) == 'created') {
					$data = array(
									'pay_status' => strtolower($response['response']['status'])
								);
					if (isset($queryParams['is_web'])) {	
						echo '<script>location.replace("/?pending=0");</script>';exit;
					} else {
						echo '<script>location.replace("'.$_server_domain_url.'/api/v1/purchase/subscribe/verify?success=1");</script>';exit;
					}
				} else  {
					$data = array(
									'pay_status' => strtolower($response['response']['status'])
								);
					if (isset($queryParams['is_web'])) {
						echo '<script>location.replace("/?success=1");</script>';exit;
					} else {
						echo '<script>location.replace("'.$_server_domain_url.'/api/v1/purchase/subscribe/verify?success=2");</script>';exit;
					}
				}
			}
		}
	}
	return renderWithJson(array(),'Please check with Administrator', '', 1);
});
$app->GET('/api/v1/fund', function ($request, $response, $args) {
    global $authUser;
	global $_server_domain_url;
	$queryParams = $request->getQueryParams();
	if (!empty($queryParams) && isset($queryParams['amount'])) {
		$paymentGateway = getPaymentDetails($queryParams['payment_gateway_id']);
		if (!empty($paymentGateway)) {
			try {
				$isWeb = '';
				if (isset($queryParams['is_web'])) {
					$isWeb = '&is_web=true';
				}
				$is_sanbox = $paymentGateway['is_test_mode'];
				$email = ($is_sanbox != 1) ? $paymentGateway['live_paypal_email'] : $paymentGateway['sanbox_paypal_email'];
				$hash = encrypt_decrypt('encrypt', $authUser->id.'/'.$queryParams['payment_gateway_id'].'/'.$is_sanbox);
				if ($paymentGateway['name'] == 'PayPal') {
					$post = array(
						'actionType' => 'PAY',
						'currencyCode' => CURRENCY_CODE,
						'receiverList' => array(
							'receiver'=> array(
								array(
									'email' => $email,
									'amount'=> numberFormat($queryParams['amount'])
								)
							)
						),
						'feesPayer' => 'SENDER',
						'requestEnvelope' => array(
							'errorLanguage' => 'en_US'
						),
						'returnUrl' => $_server_domain_url.'/api/v1/funded/verify?hash='.$hash.$isWeb,
						'cancelUrl' => $_server_domain_url.'/api/v1/funded/verify?hash='.$hash.$isWeb
					);
					$method = 'AdaptivePayments/Pay';
					$response = paypal_pay($post, $method, $paymentGateway);
					if (!empty($response) && $response['ack'] == 'success') {
						$user = Models\User::find($authUser->id);
						$user->fund_pay_key = $response['payKey'];
						$user->save();
						$data['payUrl'] = $response['payUrl'];
						$data['verifyUrl'] = $_server_domain_url.'/api/v1/funded/verify?success=0&hash='.$hash.$isWeb;
						$data['cancelUrl'] = $_server_domain_url.'/api/v1/funded/verify?success=1&hash='.$hash.$isWeb;
						return renderWithJson($data, 'Success','', 0);
					} else {	
						return renderWithJson(array(),'Paypal Server is not available right now please try after some time.', '', 1);
					}
				} else if ($paymentGateway['name'] == 'Stripe') {
					//
				} else if ($paymentGateway['name'] == 'Add Card') {
					$request = array (
					'plan' => '1', //subscription plan ID
					'email' => 'abc@xyz.com', //customer email
					'source' => array(
							'object' => 'card',
							'number' => '4242424242424242',
							'exp_month' => '08',
							'exp_year' => '2018',
							'cvc' => '123',
							'name' => 'michael Stoner',
							'address_line1' => '258 main st',
							'address_line2' => '',
							'address_city' => 'Anaheim',
							'address_state' => 'CA',
							'address_zip' => '92804',
							'address_country' => 'US',
							'currency' => 'usd',
							
						),
					);
					stripe_pay($post, true);
					$card = Models\Card::where('id', $queryParams['card_id'])->where('user_id', $authUser->id)->get()->toArray();
					if (!empty($card) && $queryParams['ccv']) {
						echo '<pre>';print_r($card);exit;
					} else {
						return renderWithJson(array(), $message = 'Invalid card or ccv', $fields = '', $isError = 1);
					}
				} else {
					return renderWithJson(array(), $message = 'Invalid Payment Gateway', $fields = '', $isError = 1);
				}
			} catch (Exception $e) {
				return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
			}
		} else {
			return renderWithJson(array(), $message = 'Invalid Payment Gateway', $fields = '', $isError = 1);
		}
	} else {
		return renderWithJson(array(), $message = 'Invalid Amount', $fields = '', $isError = 1);
	}
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->GET('/api/v1/funded/verify', function ($request, $response, $args) {
	global $_server_domain_url;
	$queryParams = $request->getQueryParams();
	if ($queryParams['hash'] != '') {
		$pay_data = explode('/',encrypt_decrypt('decrypt', $queryParams['hash']));
		$user_id = $pay_data[0];
		$payment_gateway_id = $pay_data[1];
		$is_sanbox = $pay_data[2];
		$user = Models\User::find($user_id);
		$paymentGateway = getPaymentDetails($payment_gateway_id);
		if (!empty($user) && $user->fund_pay_key != '') {			
				if (!empty($pay_data)) {					
					$post = array(
							'payKey' => $user->fund_pay_key,
							'requestEnvelope' => array(
								'errorLanguage' => 'en_US'
							)
						);
					$method = 'AdaptivePayments/PaymentDetails';
					sleep(10);
					$response = paypal_pay($post, $method, $paymentGateway);
					if (!empty($response) && $response['ack'] == 'success' && !empty($response['response'])) {
						if (strtolower($response['response']['status']) == 'completed') {
							$user->donated = $user->donated + $response['response']['paymentInfoList']['paymentInfo'][0]['receiver']['amount'];
							$user->fund_pay_key = '';
							$user->save();
							insertTransaction($user_id, 1, \Constants\TransactionClass::Fund, \Constants\TransactionType::Fund, $payment_gateway_id, $response['response']['paymentInfoList']['paymentInfo'][0]['receiver']['amount'], 0, 0, 0, 0, null, $is_sanbox, 0 ,$response['response']['paymentInfoList']['paymentInfo'][0]['transactionStatus'], $response['response']['paymentInfoList']['paymentInfo'][0]['transactionId'], $response['response']['paymentInfoList']['paymentInfo'][0]['senderTransactionId']);
							if (isset($queryParams['is_web'])) {
								echo '<script>location.replace("/?success=0");</script>';exit;
							} else {
								echo '<script>location.replace("'.$_server_domain_url.'/api/v1/funded/verify?success=0");</script>';exit;
							}
						} else if (strtolower($response['response']['status']) == 'created') {
							$data = array(
											'pay_status' => strtolower($response['response']['status'])
										);
							if (isset($queryParams['is_web'])) {
								echo '<script>location.replace("/?pending=0");</script>';exit;
							} else {
								echo '<script>location.replace("'.$_server_domain_url.'/api/v1/funded/verify?success=1");</script>';exit;
							}
						} else  {
							$data = array(
											'pay_status' => strtolower($response['response']['status'])
										);
							if (isset($queryParams['is_web'])) {
								echo '<script>location.replace("/?fail=1");</script>';exit;
							} else {
								echo '<script>location.replace("'.$_server_domain_url.'/api/v1/funded/verify?success=2");</script>';exit;
							}
						}
					}
				}
			}
	}
	return renderWithJson(array(),'Please check with Administrator', '', 1);
});
$app->GET('/api/v1/cards', function ($request, $response, $args) {
    global $authUser;
	$queryParams = $request->getQueryParams();
    $results = array();
    try {
		$count = PAGE_LIMIT;
		if (!empty($queryParams['limit'])) {
			$count = $queryParams['limit'];
		}
		$cards = Models\Card::with('user')->Filter($queryParams)->paginate($count)->toArray();
		$data = $cards['data'];
		unset($cards['data']);
		$results = array(
            'data' => $data,
            '_metadata' => $cards
        );
		return renderWithJson($results, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->POST('/api/v1/card', function ($request, $response, $args) {
    global $authUser, $_server_domain_url;
	$result = array();
    $args = $request->getParsedBody();
	if ((isset($args['card_number']) && $args['card_number'] != '') && (isset($args['ccv']) && $args['ccv'] != '') && (isset($args['expiry_date']) && $args['expiry_date'] != '')) {  
		$card = new Models\Card($args);
		$card->card_number = crypt($args['card_number'], $args['ccv']);
		$card->card_display_number = str_repeat('*', strlen($args['card_number']) - 3) . substr($args['card_number'], -3);
		try {
			$validationErrorFields = $card->validate($args);
			if (empty($validationErrorFields)) {
				$card->is_active = 1;
				$card->user_id = $authUser->id;
				if ($card->save()) {
					$result['data'] = $card->toArray();
					return renderWithJson($result, 'Success','', 0);
				} else {
					return renderWithJson(array(), 'Card could not be added. Please, try again.', '', 1);
				}
			} else {
				return renderWithJson(array(), 'Card could not be added. Please, try again.', $validationErrorFields, 1);
			}
		} catch (Exception $e) {
			return renderWithJson(array(), 'Card could not be added. Please, try again.'.$e->getMessage(), '', 1);
		}
	}
	return renderWithJson(array(), 'Card could not be added. Please, try again.', '', 1);
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->POST('/api/v1/paypal_connect', function ($request, $response, $args) {
    global $authUser;
	$args = $request->getParsedBody();
	if (!empty($args) && isset($args['email'])) {
		$isLive = false;
		$url = $isLive ? 'https://svcs.paypal.com/' : 'https://svcs.sandbox.paypal.com/';
		$tokenUrl = $url.'AdaptiveAccounts/GetVerifiedStatus';	
		try {
			$post = array(
				'actionType' => 'PAY',
				'currencyCode' => 'USD',
				'requestEnvelope' => array(
					'errorLanguage' => 'en_US'
				),
				'matchCriteria' => 'NONE',
				'emailAddress' => $args['email']
			);
			$post_string = json_encode($post);
			$header = array(
					'X-PAYPAL-SECURITY-USERID: freehidehide_api1.gmail.com',
					'X-PAYPAL-SECURITY-PASSWORD: AC3BTDPQW5DWV52W',
					'X-PAYPAL-SECURITY-SIGNATURE: AYS.KyRPCh0NqN2ORLAMv8z1H9kWAS3rJdqYkIt.XoOnKgTHdSlTxCrx',
					'X-PAYPAL-REQUEST-DATA-FORMAT: JSON',
					'X-PAYPAL-RESPONSE-DATA-FORMAT: JSON',
					'X-PAYPAL-APPLICATION-ID: APP-80W284485P519543T'
				);
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $tokenUrl);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$result = curl_exec($ch);
			if ($result) {
				$resultArray = json_decode($result, true);
				$user = Models\User::find($authUser->id);
				if (!empty($resultArray) && !empty($resultArray['responseEnvelope']) && strtolower($resultArray['responseEnvelope']['ack']) == 'success') {
					$user->is_paypal_connect = true;
					$user->paypal_email = $args['email'];
					$user->save();
					$data = array(
						'is_paypal_connect' => $user->is_paypal_connect
					);
					return renderWithJson($data, 'Success','', 0);
				} else { 
					$user->is_paypal_connect = false;
					$user->paypal_email = '';
					$user->save();
					$data = array(
						'is_paypal_connect' => $user->is_paypal_connect
					);					
					return renderWithJson($data, 'Invalid','', 1);
				}
			}
			return renderWithJson(array(),'Please check with Administrator', '', 1);
		} catch (Exception $e) {
			return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
		}
	} else {
		return renderWithJson(array(), $message = 'Email is empty', $fields = '', $isError = 1);
	}
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->DELETE('/api/v1/card/{id}', function ($request, $response, $args) {
    global $authUser;
	try {
		Models\Card::where('id', $request->getAttribute('id'))->where('user_id', $authUser->id)->delete();
		return renderWithJson(array(), 'Success','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'Card could not be delete. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->GET('/api/v1/tickets', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
		$url = 'https://www.eventbriteapi.com/v3/users/me/events/';
        $events = eventBriteExecute($url);
		$events = json_decode($events, true);
        $data = $events['events'];
		$meta = array();
		$meta['current_page'] = $events['pagination']['page_number'];
		$meta['total'] = $events['pagination']['object_count'];
		$meta['per_page'] = $events['pagination']['page_size'];
        $result = array(
            'data' => $data,
            '_metadata' => $meta
        );
        return renderWithJson($result);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
});
$app->GET('/api/v1/transactions', function ($request, $response, $args) {
	global $authUser;
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $enabledIncludes = array(
			'user',
            'other_user',
			'payment_gateway'
		);
		if (!empty($queryParams['class'])) {
			if ($queryParams['class'] == 'Product') {
				$enabledIncludes = array_merge($enabledIncludes,array('detail', 'parent_user'));
			} else if ($queryParams['class'] == 'VotePackage' || $queryParams['class'] == 'InstantPackage') {
				$enabledIncludes = array_merge($enabledIncludes,array('package', 'parent_user'));
			} else if ($queryParams['class'] == 'SubscriptionPackage') {
				$enabledIncludes = array_merge($enabledIncludes,array('subscription'));
			}
        }
        $transactions = Models\Transaction::select('created_at', 'user_id', 'to_user_id', 'parent_user_id', 'foreign_id','payment_gateway_id', 'amount')->with($enabledIncludes);
		if (!empty($authUser['id'])) {
            $user_id = $authUser['id'];
            $transactions->where(function ($q) use ($user_id) {
                $q->where('user_id', $user_id)->orWhere('to_user_id', $user_id);
            });
        }
		$transactions = $transactions->Filter($queryParams)->paginate($count);
		$transactionsNew = $transactions;
        $transactionsNew = $transactionsNew->toArray();
        $data = $transactionsNew['data'];
        unset($transactionsNew['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $transactionsNew
        );
        return renderWithJson($result);
        return renderWithJson($result);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->PUT('/api/v1/attachments', function ($request, $response, $args) {
	global $authUser;
	Models\Attachment::where('user_id', $authUser->id)->where('is_admin_approval', 0)->update(array(
					'is_admin_approval' => 1
				));
				
	 return renderWithJson(array(), 'Approval In-progress','', 0);
})->add(new ACL('canAdmin canContestantUser canCompanyUser'));
$app->PUT('/api/v1/attachments/approve/{id}', function ($request, $response, $args) {
	Models\Attachment::where('user_id', $request->getAttribute('id'))->where('is_admin_approval', 1)->update(array(
					'is_admin_approval' => 2
				));
				
	 return renderWithJson(array(), 'Approved Successfully','', 0);
})->add(new ACL('canAdmin'));
$app->POST('/api/v1/tell_a_friend', function ($request, $response, $args) use ($app)
{
	$result = array();
	$args = $request->getParsedBody();
	$result['status'] = 'Failed';
	if ($args && $args['email']) {
		$result['status'] = mail($args['email'],"Tell A Friend", $args['message']);
		if ($result['status']) {
			return renderWithJson($result, 'Success','', 0);
		}
	}
	return renderWithJson($result, 'Failed','', 0);
});
$app->POST('/api/v1/feedback', function ($request, $response, $args) use ($app)
{
	$result = array();
	$args = $request->getParsedBody();
	$result['status'] = 'Success';
	if ($args && $args['email']) {
		if ($result['status']) {
			return renderWithJson($result, 'Success','', 0);
		}
	}
	return renderWithJson($result, 'Failed','', 0);
});
$app->GET('/api/v1/country', function ($request, $response, $args) use ($app)
{
	$countries = Models\Country::select('id', 'name')->get()->toArray();
	$results = array(
		'data' => $countries
	);
	return renderWithJson($results, 'country details list fetched successfully','', 0);
});
$app->GET('/api/v1/centers', function ($request, $response, $args) use ($app)
{
	$centers = Models\Island::with('centers')->get()->toArray();
	$results = array(
		'data' => $centers
	);
	return renderWithJson($results, 'centers details list fetched successfully','', 0);
});
$app->POST('/api/v1/schedule_test', function ($request, $response, $args) use ($app)
{
	global $authUser, $_server_domain_url;
	$args = $request->getParsedBody();
	$result = array();
    try {
		//$scheduleTestExist = Models\ScheduleTest::where('reg_date', $args['reg_date'])->get();
		//$customTimeSlot = Models\CustomTimeSlot::where('center_id', $args['center_id'])->where('date_detail', $args['reg_date'])->first();
		//$isAvailable = false;
        //if (!empty($customTimeSlot)) {
          //$customTimeSlot = $customTimeSlot->toArray();
		  	
		  //echo '<pre>';print_r($customTimeSlot);exit;
		//} else {
			//$timeSlots = Models\TimeSlot::where('center_id', $args['center_id'])->where('day', strtolower(date('D', strtotime($args['reg_date']))))->first();
			//echo '<pre>';print_r($timeSlots);exit;
		//}
        $scheduleTest = new Models\ScheduleTest;
		$scheduleTest->user_id = $authUser->id;
		$scheduleTest->center_id = $args['center_id'];
		$scheduleTest->reg_date = $args['reg_date'];
		$scheduleTest->save();
        return renderWithJson(array(), 'Successfully added','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $e->getMessage(), $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->PUT('/api/v1/schedule_test', function ($request, $response, $args) use ($app)
{
	global $authUser, $_server_domain_url;
	$args = $request->getParsedBody();
	$result = array();
    try {
		Models\ScheduleTest::where('user_id', $authUser->id)->where('id', $args['id'])->update(array(
				'qr_code' => $args['qr_code']
			));
		return renderWithJson(array(), 'Update successfully','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $e->getMessage(), $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->POST('/api/v1/schedule_test/scan', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	$args = $request->getParsedBody();
	try {
		$queryParams = $request->getQueryParams();
		$result = array();
		$settings = Models\ScheduleTest::with('center')->where('qr_code', $args['qr_code'])->get();	
		$result = array();
		if (!empty($settings) && !empty($settings[0])) {
			$result['data'] = $settings[0];
			return renderWithJson($result, 'Success','', 0);
		} else {
			return renderWithJson(array(), 'Invalid code', '', 1);
		}
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->GET('/api/v1/schedule_test/detail/{id}', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	try {
		$queryParams = $request->getQueryParams();
		$result = array();
		$settings = Models\ScheduleTest::with('center')->where('id', $request->getAttribute('id'))->get();	
		$result = array();
		$result['data'] = $settings[0];
		return renderWithJson($result, 'Success','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->GET('/api/v1/schedule_test', function ($request, $response, $args) {
	global $authUser, $_server_domain_url;
	try {
		$queryParams = $request->getQueryParams();
		$result = array();
		$settings = Models\ScheduleTest::where('user_id', $authUser->id)->get();	
		$result = array();
		$result['data'] = $settings;
		return renderWithJson($result, 'Success','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'error', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canUser canContestantUser canCompanyUser'));
$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function($req, $res) {
    $handler = $this->notFoundHandler; // handle using the default Slim page not found handler
    return $handler($req, $res);
});
$app->run();
