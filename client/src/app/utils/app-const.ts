
export class AppConst {
    public static readonly SERVER_URL = {
        REGISTER: '/users/register',
        LOGIN: '/users/login',
        SOCIAL_LOGIN: '/users/social_login',
        USER: '/users',
        FORGETPASSWORD: '/users/forgot_password',
        CHANGEPASSWORD: '/users/change_password',
        ALLCATEGORY: '/catagories',
        CONTESTANTS: '/contestants',
        HIGHEST_VOTES: '/contestants/highest_votes',
        RECENT_WINNER: '/contestants/recent_winner',
        ADVERTISEMENTS: '/advertisements',
        PRODUCTS: '/products',
        PRODUCT: '/product',
        ATTACHMENTS: '/attachments',
        SETTINGS: '/settings?is_web=true',
        TRANSACTIONS: '/transactions',
        VOTEPACKAGES: '/vote_packages',
        VOTEPACKAGE: '/vote_package',
        CONTEST: '/contest',
        FUND: '/fund',
        PAYMENT_GATEWAYS: '/payment_gateways',
        SUBSCRIPTION: '/purchase/subscription/1',
        VOTE_PURCHASE: '/purchase/vote_package/',
        USER_CATEGORY: '/user_category/',
        INSTANT_VOTE_PURCHASE: '/purchase/contest/',
        CART: '/cart',
        OFFLINECART: '/offline/cart',
        CART_PURCHASE: '/purchase/cart',
        USER_ADDRESS: '/user_address',
        INSTANT_WINNER: '/instant_vote',
        PAGES: '/pages',
        USER_IMAGE: '/user_image',
        SIZES: '/sizes',
        TIMESLOTS: '/time_slots',
        CUSTOM_TIMESLOTS: '/custom_time_slots',
        STATIC: '/static',
        RESTAURANTS: '/admin/restaurants',
        RESTAURANTS_LIST: '/restaurant_list',
        RESTAURANTDETAILS: '/admin/restaurant/detail',
        RESTAURANTDELETE: '/admin/restaurant/delete',
        LOGOUT: '/users/logout'
    };

    public static readonly NON_AUTH_SERVER_URL = [
        '/users/register',
        '/users/login',
        '/users/forgot_password',
        '/change_password',
        '/catagories',
        '/contestants',
        '/advertisements',
        '/settings?is_web=true',
        '/contestants/highest_votes',
        '/contestants/recent_winner',
        '/pages',
        '/pages/aboutus',
        '/pages/term-and-conditions',
        '/pages/privacy',
        '/pages/how-it-works',
        '/pages/aup',
        '/pages/faq',
        '/vote_packages',
        '/tickets'
    ];

    public static readonly SERVICE_STATUS = {
        SUCCESS: 0,
        FAILED: 1
    };

    public static readonly ROLE = {
        ADMIN: 1,
        USER: 2,
        EMPLOYER: 3,
        COMPANY: 4
    };

    public static readonly MONTH_NAMES = [
        'Jan',
        'Feb',
        'Mar',
        'Apr',
        'May',
        'Jun',
        'Jul',
        'Aug',
        'Sep',
        'Oct',
        'Nov',
        'Dec'
    ];

    public static readonly WEEK_DAYS = [
        'Sun',
        'Mon',
        'Tue',
        'Wed',
        'Thu',
        'Fri',
        'Sat'
    ];
}
