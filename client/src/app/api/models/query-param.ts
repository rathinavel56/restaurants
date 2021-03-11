
export interface QueryParam {
    id?: number;
    page?: number;
    category_id?: number | string;
    sort?: string;
    sortby?: string;
    class?: string;
    is_web?: boolean;
    contest_id?: number;
    payment_gateway_id?: number;
    amount?: number;
    is_active?: boolean;
    contestant_id?: string;
    username?: string;
    product_detail_id?: number;
    product_size_id?: number;
    quantity?: number;
    coupon_code?: string;
    q?: string;
    role_id?: number;
    is_purchase?: boolean;
    user_address_id?: number;
    from?: string;
    to?: string;
    location?: string;
    caption?: string;
    filter_by?: string;
    ispaid?: number;
    type?: string;
    date_detail?: string;
    search?: string;
    restaurant_id?: any;
}
