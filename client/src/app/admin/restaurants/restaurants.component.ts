
import { Component, OnInit } from '@angular/core';
import { routerTransition } from '../../router.animations';
import { ToastService } from '../../api/services/toast-service';
import { UserService } from '../../api/services/user.service';
import { CrudService } from '../../api/services/crud.service';
import { AppConst } from '../../utils/app-const';
import { QueryParam } from '../../api/models/query-param';
declare const google: any;
@Component({
    selector: 'app-restaurant',
    templateUrl: './restaurants.component.html',
    styleUrls: ['./restaurants.component.scss'],
    animations: [routerTransition()]
})
export class RestaurantComponent implements OnInit {

    constructor(private toastService: ToastService,
        private userService: UserService,
        private crudService: CrudService) { }
    isAllfacilitity: any = false;
    isAtmospheres: any = false;
    isLanguages: any = false;
    isThemes: any = false;
    isCuisines: any = false;
    staticDataList: any;
    restaurantDetails: any;
    isAddEdit: any = false;
    restaurants: any = [];
    editMode: any = false;
    viewMode: any = false;
    sessionService: any;
    search: any;
    geocoder: any;
    timeSlots: any = [];
    
    ngOnInit() {
        this.geocoder = new google.maps.Geocoder();
        this.sessionService = JSON.parse(sessionStorage.getItem('user_context'));
        if (this.sessionService.role_id === 3) {
            this.getStaticData();
        } else {
            this.getRestaurants();
            this.getStaticData();
        }
        this.timeSlots.push('Select');
        for (let i = 0; i <= 23; i++) {
            let timeValue = (i.toString().length === 1) ? ('0' + i) : i;
            this.timeSlots.push(timeValue + ':00');
            this.timeSlots.push(timeValue + ':30');
        }
        google.maps.event.addDomListener(window, 'load', this.initialize);
    }

    addEdit() {
        this.reset();
        this.isAddEdit = !this.isAddEdit;
        let thiss = this;
        setTimeout(function () {
            thiss.populateMarker(38.9637, 35.2433);
        }, 2000);
    }

    getRestaurants() {
        this.toastService.showLoading();
        this.userService.restaurants({
            search: this.search ? this.search : undefined
        })
            .subscribe((response) => {
                if (response.data) {
                    this.restaurants = response.data;
                }
                this.getStaticData();
                this.toastService.clearLoading();
            });
    }

    edit(id, isView) {
        this.editMode = true;
        this.viewMode = isView;
        this.toastService.showLoading();
        this.userService.restaurantDetail(id)
            .subscribe((response) => {
                if (response.data) {
                    this.restaurantDetails = {
                        id: id,
                        title: response.data.title,
                        description: response.data.description,
                        address: response.data.address,
                        email: response.data.user.email,
                        city: response.data.city ? response.data.city.name : undefined,
                        state: response.data.state,
                        country: response.data.country ? response.data.country.name : undefined,
                        disclaimer: response.data.disclaimer,
                        latitude: response.data.latitude,
                        longitude: response.data.longitude,
                        maxperson: response.data.max_person,
                        operating_hours: (response.data.operating_hours && response.data.operating_hours.length > 0) ? response.data.operating_hours : this.getOperatingHours(),
                        specialConditions: [{
                            name: ''
                        }],
                        facilities: [{
                            facilities_service_id: ''
                        }],
                        menus: [{
                            name: '',
                            price: ''
                        }],
                        atmospheres: [{
                            atmosphere_id: ''
                        }],
                        languages: [],
                        payments: [],
                        themes: [],
                        cuisines: [],
                        facilitity_others: '',
                        atmospheres_others: '',
                        languages_others: '',
                        themes_others: '',
                        cuisines_others: '',
                        about: '',
                        attachments: [],
                        attachmentsDeleted: [],
                        is_active: response.data.is_active,
                        is_admin_deactived: (this.sessionService.role_id === 1) ? response.data.is_admin_deactived : undefined
                    };
                    if (response.data.facilities_services && response.data.facilities_services.length > 0) {
                        this.staticDataList.facilities.forEach(facility => {
                            facility.selected = (response.data.facilities_services.filter((e) => facility.id === e.facilities_service_id).length > 0);
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
                        this.staticDataList.atmospheres.forEach(atmosphere => {
                            atmosphere.selected = (response.data.atmospheres.filter((e) => atmosphere.id === e.atmosphere_id).length > 0);
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
                    if (response.data.themes && response.data.themes.length > 0) {
                        this.staticDataList.themes.forEach(theme => {
                            theme.selected = (response.data.themes.filter((e) => theme.id === e.theme_id).length > 0);
                        });
                    }
                    if (response.data.cuisines && response.data.cuisines.length > 0) {
                        this.staticDataList.cuisines.forEach(cuisine => {
                            cuisine.selected = (response.data.cuisines.filter((e) => cuisine.id === e.cuisine_id).length > 0);
                        });
                    }
                    if (response.data.about) {
                        this.restaurantDetails.about = response.data.about.about;
                    }
                    this.restaurantDetails.attachments = response.data.attachments;
                    this.isAddEdit = !this.isAddEdit;
                    let thiss = this;
                    setTimeout(function () {
                        thiss.populateMarker(thiss.restaurantDetails.latitude, thiss.restaurantDetails.longitude);
                    }, 2000);                    
                }
                this.toastService.clearLoading();
            });
    }

    delete(index, id) {
        this.toastService.showLoading();
        this.userService.restaurantDelete(id)
            .subscribe((response) => {
                this.restaurants.splice(index, 1);
                this.toastService.clearLoading();
                this.toastService.success(response.error.message);
                this.ngOnInit();
            });
    }

    reset() {
        this.viewMode = false;
        this.restaurantDetails = {
            id: null,
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
            facilitity_others: '',
            atmospheres_others: '',
            languages_others: '',
            themes_others: '',
            cuisines_others: '',
            operating_hours: this.getOperatingHours(),
            specialConditions: [{
                name: ''
            }],
            facilities: [{
                facilities_service_id: ''
            }],
            menus: [{
                name: '',
                price: ''
            }],
            atmospheres: [{
                atmosphere_id: ''
            }],
            languages: [],
            payments: [],
            themes: [],
            cuisines: [],
            about: '',
            attachments: [],
            attachmentsDeleted: [],
            is_active: true,
            is_admin_deactived: (this.sessionService.role_id === 1) ? 0 : undefined
        };
    }
    
    getOperatingHours() {
        return [{
            day: 'Sun',
            holiday: false,
            hours: [{
                name: 'Breakfast',
                type: 1,
                start_time: 'Select',
                end_time: 'Select'
            },{
                name: 'Lunch',
                type: 2,
                start_time: 'Select',
                end_time: 'Select'
            },
            {
                name: 'Dinner',
                type: 3,
                start_time: 'Select',
                end_time: 'Select'
            }]
        },{
            day: 'Mon',
            holiday: false,
            hours: [{
                name: 'Breakfast',
                type: 1,
                start_time: 'Select',
                end_time: 'Select'
            },{
                name: 'Lunch',
                type: 2,
                start_time: 'Select',
                end_time: 'Select'
            },
            {
                name: 'Dinner',
                type: 3,
                start_time: 'Select',
                end_time: 'Select'
            }]
        },{
            day: 'Tue',
            holiday: false,
            hours: [{
                name: 'Breakfast',
                type: 1,
                start_time: 'Select',
                end_time: 'Select'
            },{
                name: 'Lunch',
                type: 2,
                start_time: 'Select',
                end_time: 'Select'
            },
            {
                name: 'Dinner',
                type: 3,
                start_time: 'Select',
                end_time: 'Select'
            }]
        },{
            day: 'Wed',
            holiday: false,
            hours: [{
                name: 'Breakfast',
                type: 1,
                start_time: 'Select',
                end_time: 'Select'
            },{
                name: 'Lunch',
                type: 2,
                start_time: 'Select',
                end_time: 'Select'
            },
            {
                name: 'Dinner',
                type: 3,
                start_time: 'Select',
                end_time: 'Select'
            }]
        },{
            day: 'Thu',
            holiday: false,
            hours: [{
                name: 'Breakfast',
                type: 1,
                start_time: 'Select',
                end_time: 'Select'
            },{
                name: 'Lunch',
                type: 2,
                start_time: 'Select',
                end_time: 'Select'
            },
            {
                name: 'Dinner',
                type: 3,
                start_time: 'Select',
                end_time: 'Select'
            }]
        },{
            day: 'Fri',
            holiday: false,
            hours: [{
                name: 'Breakfast',
                type: 1,
                start_time: 'Select',
                end_time: 'Select'
            },{
                name: 'Lunch',
                type: 2,
                start_time: 'Select',
                end_time: 'Select'
            },
            {
                name: 'Dinner',
                type: 3,
                start_time: 'Select',
                end_time: 'Select'
            }]
        },{
            day: 'Sat',
            holiday: false,
            hours: [{
                name: 'Breakfast',
                type: 1,
                start_time: 'Select',
                end_time: 'Select'
            },{
                name: 'Lunch',
                type: 2,
                start_time: 'Select',
                end_time: 'Select'
            },
            {
                name: 'Dinner',
                type: 3,
                start_time: 'Select',
                end_time: 'Select'
            }]
        }];
    }

    public handleAddressChange(address: any) {
        if (address.address_components) {
            this.restaurantDetails.address = address.formatted_address;
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
        let thiss = this;
        let preview = document.querySelector('#preview');
        Array.from(event.target.files).forEach((file: any, index) => {
            let reader = new FileReader();
            reader.onload = (e: any) => {
                thiss.restaurantDetails.attachments.push({ src: e.target.result });
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

    softDelete(index) {
        this.restaurantDetails.attachments.splice(index, 1);
    }

    hardDelete(index, attachmentId) {
        this.restaurantDetails.attachments.splice(index, 1);
        this.restaurantDetails.attachmentsDeleted.push(attachmentId);
    }

    getStaticData() {
        this.toastService.showLoading();
        this.userService.static({}).subscribe((response) => {
            if (this.sessionService.role_id === 3) {
                this.reset();
                this.edit(this.sessionService.id, false);
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
            atmosphere_id: ''
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
            facilities_service_id: ''
        });
    }
    saveRes() {
        if (!this.editMode && this.restaurantDetails.username.trim() === '') {
            this.toastService.error('Name is required');
        } else if (!this.editMode && this.restaurantDetails.password.trim() === '') {
            this.toastService.error('Password is required');
        } else if (!this.editMode && this.restaurantDetails.confirmpassword.trim() === '') {
            this.toastService.error('Confirm Password is required');
        } else if (!this.editMode && this.restaurantDetails.password !== this.restaurantDetails.confirmpassword) {
            this.toastService.error('Password and Confirm Password is required');
        } else if (this.restaurantDetails.address.trim() === '') {
            this.toastService.error('Address is required');
        } else if (this.restaurantDetails.latitude === '') {
            this.toastService.error('Latitude is required');
        } else if (this.restaurantDetails.longitude === '') {
            this.toastService.error('Longitude is required');
        } else if (this.restaurantDetails.description.trim() === '') {
            this.toastService.error('Description is required');
        } else if (this.restaurantDetails.maxperson <= 0) {
            this.toastService.error('Maxperson is required');
        } else if (this.restaurantDetails.menus.filter((e) => e.name.trim() === '' || e.price === '').length > 0) {
            this.toastService.error('Menus is required');
        } else if (this.staticDataList.facilities.filter((e) => e.selected === true).length === 0) {
            if (this.restaurantDetails.facilitity_others.trim() === '') {
                this.toastService.error('Facilities is required');
            }
        } else if (this.staticDataList.atmospheres.filter((e) => e.selected === true).length === 0) {
            if (this.restaurantDetails.atmospheres_others.trim() === '') {
                this.toastService.error('Atmosphere is required');
            }
        } else if (this.staticDataList.themes.filter((theme) => theme.selected === true).length === 0) {
            if (this.restaurantDetails.themes_others.trim() === '') {
                this.toastService.error('Theme is required');
            }
        } else if (this.staticDataList.cuisines.filter((cuisine) => cuisine.selected === true).length === 0) {
            if (this.restaurantDetails.cuisines_others.trim() === '') {
                this.toastService.error('Cuisine is required');
            }
        } else if (this.staticDataList.languages.filter((language) => language.selected === true).length === 0) {
            if (this.restaurantDetails.languages_others.trim() === '') {
                this.toastService.error('Languages is required');
            }
        } else if (this.staticDataList.payments.filter((payment) => payment.selected === true).length === 0) {
            this.toastService.error('Payments is required');
        } else if (this.restaurantDetails.about.trim() === '') {
            this.toastService.error('About is required');
        } else if (this.restaurantDetails.attachments.length === 0) {
            this.toastService.error('Images is required');
        } else {
            this.restaurantDetails.themes = this.staticDataList.themes.filter((theme) => theme.selected === true);
            this.restaurantDetails.cuisines = this.staticDataList.cuisines.filter((cuisine) => cuisine.selected === true);
            this.restaurantDetails.languages = this.staticDataList.languages.filter((language) => language.selected === true);
            this.restaurantDetails.payments = this.staticDataList.payments.filter((payment) => payment.selected === true);
            this.restaurantDetails.attachments = this.restaurantDetails.attachments.filter((attachment) => (!attachment.src && !attachment.id));
            this.restaurantDetails.specialConditions = this.restaurantDetails.specialConditions.filter((e) => e.name.trim() !== '');
            this.restaurantDetails.atmospheres = this.staticDataList.atmospheres.filter((e) => e.selected === true);
            this.restaurantDetails.facilities = this.staticDataList.facilities.filter((e) => e.selected === true);
            this.toastService.showLoading();
            if (this.editMode) {
                this.userService.restaurantEdit(this.restaurantDetails)
                    .subscribe((response) => {
                        if (response.error && response.error.code === AppConst.SERVICE_STATUS.SUCCESS) {
                            this.toastService.success(response.error.message);
                            this.reset();
                            this.getRestaurants();
                            this.isAddEdit = false;
                        } else {
                            this.toastService.error(response.error.message);
                        }
                        this.toastService.clearLoading();
                    });
            } else {
                this.userService.restaurantSave(this.restaurantDetails)
                    .subscribe((response) => {
                        if (response.error && response.error.code === AppConst.SERVICE_STATUS.SUCCESS) {
                            this.toastService.success(response.error.message);
                            this.reset();
                            this.getRestaurants();
                            this.isAddEdit = false;
                        } else {
                            this.toastService.error(response.error.message);
                        }
                        this.toastService.clearLoading();
                    });
            }
        }
    }
    geocodePosition(pos) {
        let thiss = this;
        this.geocoder.geocode({
          latLng: pos
        }, function(responses) {
          if (responses && responses.length > 0) {
            responses[0].address_components.forEach(element => {
                if (element.types.indexOf('locality') > -1) {
                    thiss.restaurantDetails.city = element.long_name;
                }
                if (element.types.indexOf('administrative_area_level_1') > -1) {
                    thiss.restaurantDetails.state = element.long_name;
                }
                if (element.types.indexOf('country') > -1) {
                    thiss.restaurantDetails.country = element.long_name;
                }
            });
            thiss.updateMarkerAddress(responses[0].formatted_address);
          } else {
            thiss.updateMarkerAddress('Cannot determine address at this location.');
          }
        });
    }
    updateMarkerPosition(latLng) {
        this.restaurantDetails.latitude = latLng.lat();
        this.restaurantDetails.longitude = latLng.lng();
    }
    updateMarkerAddress(str) {
        this.restaurantDetails.address = str;
    }
    initialize() {
        let thiss = this;
        let input = document.getElementById('searchTextField');
        let autocomplete = new google.maps.places.Autocomplete(input);
        google.maps.event.addListener(autocomplete, 'place_changed', function () {
            let place = autocomplete.getPlace();
            thiss.restaurantDetails.city = place.name;
            thiss.populateMarker(place.geometry.location.lat(), place.geometry.location.lng());
        });
        this.populateMarker(-34.397, 150.644);
    }
    populateMarker(lat, lon) {
        let thiss = this;
        let latLng = new google.maps.LatLng(lat, lon);
        let map = new google.maps.Map(document.getElementById('mapCanvas'), {
            zoom: 8,
            center: latLng,
            mapTypeId: google.maps.MapTypeId.ROADMAP
        });
        let marker = new google.maps.Marker({
            position: latLng,
            title: 'Point A',
            map: map,
            draggable: true
        });
    
        // Update current position info.
        this.updateMarkerPosition(latLng);
        this.geocodePosition(latLng);
    
        // Add dragging event listeners.
        google.maps.event.addListener(marker, 'dragstart', function() {
            thiss.updateMarkerAddress('');
        });
    
        google.maps.event.addListener(marker, 'drag', function() {
            thiss.updateMarkerPosition(marker.getPosition());
        });
    
        google.maps.event.addListener(marker, 'dragend', function() {
            thiss.geocodePosition(marker.getPosition());
        });
    }
}
