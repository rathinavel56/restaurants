
import { Component, OnInit } from '@angular/core';
import { routerTransition } from '../../router.animations';
import { ToastService } from '../../api/services/toast-service';
import { UserService } from '../../api/services/user.service';
import { CrudService } from '../../api/services/crud.service';
import { AppConst } from '../../utils/app-const';
import { QueryParam } from '../../api/models/query-param';
@Component({
    selector: 'app-restaurant',
    templateUrl: './restaurants.component.html',
    styleUrls: ['./restaurants.component.scss'],
    animations: [routerTransition()]
})
export class RestaurantComponent implements OnInit {
    
    constructor(private toastService: ToastService,
        private userService: UserService,
        private crudService: CrudService) {}
    staticDataList: any; 
    timeSlots: any = [];
    restaurantDetails: any;
    isAddEdit: any = false;
    restaurants: any = [];
    editMode: any = false;
    sessionService: any;

    ngOnInit() {
        this.sessionService = JSON.parse(sessionStorage.getItem('user_context'));
        if (this.sessionService.role_id === 3) {
            this.getStaticData();
        } else {
            this.getRestaurants();
            this.getStaticData();
        }
    }

    addEdit() {
        this.reset();
        this.isAddEdit = !this.isAddEdit;
    }

    getRestaurants() {        
        this.userService.restaurants({})
        .subscribe((response) => {
            if (response.data) {
                this.restaurants = response.data;
            }
            this.toastService.clearLoading();
        });
    }

    edit(id) {
        this.editMode = true;
        this.userService.restaurantDetail(id)
        .subscribe((response) => {
            if (response.data) {                
                this.restaurantDetails = {
                    title: response.data.title,
                    description: response.data.description,
                    address: response.data.address,
                    email: response.data.user.email,
                    city: response.data.city.name,
                    state: response.data.state,
                    country: response.data.country.name,
                    disclaimer: response.data.disclaimer,
                    latitude: response.data.latitude,
                    longitude: response.data.longitude,
                    maxperson: response.data.max_person,
                    specialConditions: [{
                        name: ''
                    }],
                    facilities: [{
                        name: ''
                    }],
                    menus: [{
                        name: '',
                        price: ''
                    }],
                    atmospheres: [{
                        name: ''
                    }],
                    languages: [],
                    payments: [],
                    about: '',
                    attachments: []
                };
                if (response.data.facilities_services && response.data.facilities_services.length > 0) {
                    this.restaurantDetails.facilities = [];
                    response.data.facilities_services.forEach(facility => {
                        this.restaurantDetails.facilities.push({
                            name: facility.name
                        });
                    });
                }
                if (response.data.menus && response.data.menus.length > 0) {
                    this.restaurantDetails.menus = [];
                    response.data.menus.forEach(menu => {
                        this.restaurantDetails.menus.push({
                            name: menu.name,
                            price: menu.price
                        });
                    });
                }
                if (response.data.special_conditions && response.data.special_conditions.length > 0) {
                    this.restaurantDetails.specialConditions = [];
                    response.data.special_conditions.forEach(specialCondition => {
                        this.restaurantDetails.specialConditions.push({
                            name: specialCondition.condition
                        });
                    });
                }
                if (response.data.atmospheres && response.data.atmospheres.length > 0) {
                    this.restaurantDetails.atmospheres = [];
                    response.data.atmospheres.forEach(atmosphere => {
                        this.restaurantDetails.atmospheres.push({
                            name: atmosphere.name
                        });
                    });
                }
                if (response.data.languages && response.data.languages.length > 0) {
                    this.staticDataList.languages.forEach(language => {
                        language.selected = (response.data.languages.filter((e) => language.id === e.language_id).length > 0); 
                    });
                }
                if (response.data.payment && response.data.payment.length > 0) {
                    this.staticDataList.payments.forEach(payment => {
                        payment.selected = (response.data.payment.filter((e) => payment.id === e.payment_id).length > 0); 
                    });
                }
                if (response.data.about) {
                    this.restaurantDetails.about = response.data.about.about;
                }
                this.restaurantDetails.attachments = response.data.attachments;
                this.isAddEdit = !this.isAddEdit;
            }
            this.toastService.clearLoading();
        });
    }

    delete(id) {
        this.userService.restaurantDelete(id)
        .subscribe((response) => {
            if (response.data) {
                
            }
            this.toastService.clearLoading();
        });
    }

    reset() {
        this.restaurantDetails = {
            title: '',
            username: '',
            password: '',
            confirmpassword: '',
            description: '',
            address: '',
            email: '',
            city: '',
            state: '',
            country: '',
            disclaimer: '',
            latitude: '',
            longitude: '',
            maxperson: 6,
            specialConditions: [{
                name: ''
            }],
            facilities: [{
                name: ''
            }],
            menus: [{
                name: '',
                price: ''
            }],
            atmospheres: [{
                name: ''
            }],
            languages: [],
            payments: [],
            about: '',
            attachments: []
        };
    }

    public handleAddressChange(address: any) {
        if (address.address_components) {
            this.restaurantDetails.address = address.formatted_address
            address.address_components.forEach(element => {
                if (element.types.indexOf('locality') > -1) {
                    this.restaurantDetails.city = element.long_name;
                }
                if (element.types.indexOf('administrative_area_level_1') > -1) {
                    this.restaurantDetails.state = element.long_name;
                }
                if (element.types.indexOf('country') > -1) {
                    this.restaurantDetails.country = element.long_name;
                }                  
            });
        } else {
            this.restaurantDetails.address = '';
            this.restaurantDetails.city = '';
            this.restaurantDetails.state = '';
            this.restaurantDetails.country = '';
        }
    }

    uploadImage(event) {
        this.toastService.showLoading();
        const formData: any = new FormData();
        let preview = document.querySelector('#preview');
        Array.from(event.target.files).forEach((file: any, index) => {
            let reader = new FileReader();
            reader.onload = (e: any) => {
                var image = new Image();
                image.height = 100;
                image.title = file.name;
                image.src = e.target.result;
                preview.appendChild(image);
            };
            reader.readAsDataURL(file);
            formData.append('file[]', file, file.name);
        });     
        const queryParam: QueryParam = {
            class: 'Restaurant'
        };
        this.crudService.postFile('/attachments_mutiple', formData, queryParam)
        .subscribe((response) => {
            response.attachments.forEach((e) => {
                this.restaurantDetails.attachments.push(e);
            });
            this.toastService.clearLoading();
        });
    }

    getStaticData() {
        this.toastService.showLoading();
        this.userService.static({}).subscribe((response) => {
            if (this.sessionService.role_id === 3) {
                this.reset();
                this.edit(this.sessionService.id);
            }
            if (response.data) {
                this.staticDataList = response.data;
            }
            this.toastService.clearLoading();
        });
    }
    specialAdd() {
        this.restaurantDetails.specialConditions.push({
            name: ''
        });
    }
    atmosphereAdd() {
        this.restaurantDetails.atmospheres.push({
            name: ''
        });
    }
    menuAdd() {
        this.restaurantDetails.menus.push({
            name: '',
            price: ''
        });
    }
    facilitiesAdd() {
        this.restaurantDetails.facilities.push({
            name: ''
        });
    }
    saveRes() {
        if (this.restaurantDetails.username.trim() === '') {
            this.toastService.error('Name is required');
        } else if (this.restaurantDetails.password.trim() === '') {
            this.toastService.error('Password is required');    
        } else if (this.restaurantDetails.confirmpassword.trim() === '') {
            this.toastService.error('Confirm Password is required');
        } else if (this.restaurantDetails.password !== this.restaurantDetails.confirmpassword) {
            this.toastService.error('Password and Confirm Password is required');
        } else if (this.restaurantDetails.address.trim() === '') {
            this.toastService.error('Address is required');
        } else if (this.restaurantDetails.latitude.trim() === '') {
            this.toastService.error('Latitude is required');
        } else if (this.restaurantDetails.longitude.trim() === '') {
            this.toastService.error('Longitude is required');
        } else if (this.restaurantDetails.description.trim() === '') {
            this.toastService.error('Description is required');
        } else if (this.restaurantDetails.maxperson <= 0) {
            this.toastService.error('Maxperson is required');
        } else if (this.restaurantDetails.menus.filter((e) => e.name.trim() === '' || e.price.trim() === '').length > 0) {
            this.toastService.error('Menus is required');
        } else if (this.restaurantDetails.facilities.filter((e) => e.name.trim() === '').length > 0) {
            this.toastService.error('Facilities is required');
        } else if (this.staticDataList.languages.filter((language) => language.selected === true).length === 0) {
            this.toastService.error('Languages is required');
        } else if (this.staticDataList.payments.filter((payment) => payment.selected === true).length === 0) {
            this.toastService.error('Payments is required');
        } else if (this.restaurantDetails.about.trim() === '') {
            this.toastService.error('About is required');
        } else if (this.restaurantDetails.attachments.length === 0) {
            this.toastService.error('Images is required');
        } else {
            this.restaurantDetails.languages = this.staticDataList.languages.filter((language) => language.selected === true);
            this.restaurantDetails.payments = this.staticDataList.payments.filter((payment) => payment.selected === true);
            this.userService.restaurantSave(this.restaurantDetails)
            .subscribe((response) => {
                if (response.error && response.error.code === AppConst.SERVICE_STATUS.SUCCESS) {
                    this.toastService.success(response.error.message);
                    this.reset();
                } else {
                    this.toastService.error(response.error.message);
                }
                this.toastService.clearLoading();
            });
        }
    }
}
