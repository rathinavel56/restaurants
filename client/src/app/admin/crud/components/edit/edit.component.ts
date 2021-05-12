import { Component, OnInit, Input } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { CrudService } from '../../../../api/services/crud.service';
import { ToastService } from '../../../../api/services/toast-service';
import { SessionService } from '../../../../api/services/session-service';
import { QueryParam } from '../../../../api/models/query-param';
import { AppConst } from '../../../../utils/app-const';
import * as dot from 'dot-object';
import {Location} from '@angular/common';
import { TagsChangedEvent } from 'ngx-tags-input/public-api';

declare const google: any;
@Component({
  selector: 'app-edit',
  templateUrl: './edit.component.html',
  styleUrls: ['./edit.component.scss']
})
export class EditComponent implements OnInit {

  public apiEndPoint: string;
  public menu: any;
  public responseData: any;
  public settings: any;
  public menuEditFields: any;
  public windowData: any = window;
  public isFirstTime: any = false;
  geocoder: any;

  constructor(private activatedRoute: ActivatedRoute,
    private crudService: CrudService,
    private toastService: ToastService,
    private sessionService: SessionService,
    private _location: Location,
    public router: Router) {
      let thiss = this;
      this.windowData.top.editFunc = function (value) {
        if (!thiss.isFirstTime) {
          setTimeout(() => {
            thiss.menuItem(value);
            thiss.isFirstTime = true;
          }, 500);
        } else {
            thiss.menuItem(value);
        }
      };
    }

    ngOnInit(): void {
      this.geocoder = new google.maps.Geocoder();
      google.maps.event.addDomListener(window, 'load', this.initialize);
    }
    
    menuItem(value: any) {
      if (value) {
        this.menu = value;
        if (this.menu.api !== '/admin/settings' && this.menu.api !== '/admin/payment_gateways') {
          this.menu.edit.fields.forEach((element, index) => {
            if (element.type === 'tags' || element.type === 'select') {
              if (element.reference) {
                this.crudService.get(element.reference, null)
                .subscribe((response) => {
                  element.options = response.data;
                  if (element.type === 'tags') {
                    this.setTags(element);
                  }
                });
              }
              element.value = [];
            } else {
              element.value = '';
            }
          });
          this.menuEditFields = this.menu.edit.fields;
        }
        this.getRecords();
      }
    }

    getRecords() {
      const endPoint = this.menu.api + '/' + this.activatedRoute.snapshot.paramMap.get('id');
      this.toastService.showLoading();
        this.crudService.get(endPoint, null)
        .subscribe((response) => {
            this.responseData = response.data;
            const formatObj = {};
            dot.dot(this.responseData, formatObj);
            this.menuEditFields = (this.menu.api === '/admin/settings' ||
            this.menu.api === '/admin/payment_gateways') ? this.responseData : this.menu.edit.fields;
            if (this.menu.api !== '/admin/payment_gateways') {
              this.menuEditFields.forEach(element => {
                if (element.type === 'select') {
                  if (element.reference) {
                        this.crudService.get(element.reference, null)
                          .subscribe((responseRef) => {
                            element.options = responseRef.data;
                          });
                  } else if (element.option_values) {
                    element.options = element.option_values.split(',');
                  }
                } else if (element.type === 'tags') {
                    element.value = this.responseData[element.name];
                    this.setTags(element);
                } else if (element.type === 'file') {
                  element.value = (this.responseData['attachment'] && this.responseData['attachment']['filename'])
                  ? this.responseData['attachment']['filename'] : '';
                } else if (formatObj[element.name]) {
                  element.value = formatObj[element.name];
                }
              });
            }
            let lat: any = 24.578048697375753;
            let long: any = -77.88542495468748;
            let showMap: any = false;
            if (this.menu.add && this.menu.add.fields) {
              let index: any = this.menu.add.fields.findIndex((e) => (e.name === 'address.latitude'));
              if (index > -1) {
                showMap = true;
                if (this.menu.add.fields[index]['value']) {
                  lat = this.menu.add.fields[index]['value'];
                }
              }
              index = this.menu.add.fields.findIndex((e) => (e.name === 'address.longitude'));
              if (index > -1 && this.menu.add.fields[index]['value']) {
                long = this.menu.add.fields[index]['value'];
              }
              index = this.menu.add.fields.findIndex((e) => (e.name === 'lat'));
              if (index > -1) {
                showMap = true;
                if (this.menu.add.fields[index]['value']) {
                  lat = this.menu.add.fields[index]['value'];
                }
              }
              index = this.menu.add.fields.findIndex((e) => (e.name === 'lon'));
              if (index > -1 && this.menu.add.fields[index]['value']) {
                long = this.menu.add.fields[index]['value'];
              }
              if (showMap) {
                let thiss = this;
                setTimeout(function () {
                  thiss.populateMarker(lat, long);
                }, 2000);              
              }
            }
            this.toastService.clearLoading();
        });
    }

    onTagsChangedEventHandler(event: TagsChangedEvent, item): void {
      if (event.change === 'add') {
        const index = item.options.findIndex(x => (x.id === event.tag.id));
        item.options.splice(index, 1);
      } else {
        const index = item.value.findIndex(x => (x.id === event.tag.id));
        item.options.push(event.tag);
      }
      item.options.sort((a, b) => (a.name > b.name) ? 1 : -1);
    }

    setTags(item): void {
      item.value.forEach(element => {
        const index = item.options.findIndex(x => (x.id === element.id));
        item.options.splice(index, 1);
      });
      item.options = item.options.filter((v, i, a) => a.findIndex(t => (t.id === v.id)) === i);
      item.options.sort((a, b) => (a.name > b.name) ? 1 : -1);
    }

    uploadImage(event, item) {
      this.toastService.showLoading();
      const formData: any = new FormData();
      formData.append('file', event.target.files[0], event.target.files[0].name);
        const queryParam: QueryParam = {
          class: item.imageclass ? item.imageclass : 'UserAvatar'
        };
      this.crudService.postFile('/attachments', formData, queryParam)
      .subscribe((response) => {
        if (response.error && response.error.code === AppConst.SERVICE_STATUS.SUCCESS) {
            item.file = response.attachment;
            item.isUploaded = true;
        } else {
            this.toastService.error(response.error.message);
        }
        this.toastService.clearLoading();
      });
    }

    submit() {
      const inValid = [];
      const formData = {};
      const reg = /^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,4})$/;
      this.menuEditFields.forEach((element, index) => {
        if (element.name !== 'username') {
          if ((element.is_required && element.is_required !== 0) && (Array.isArray(element.value)
          && element.value.length === 0) || ((element.is_required && element.is_required !== 0) && !element.isNotEdit &&
          !Array.isArray(element.value) && element.value.toString().trim() === '')) {
            inValid.push(element.label);
          } else if (element.name === 'email' && reg.test(element.value) === false) {
            inValid.push('Enter the valid email');
          } else {
            if (element.type === 'file') {
              formData[element.name] = (element.file !== '') ? element.file : '';
            } else {
              formData[element.name] = element.value;
            }
          }
        }
      });
      if (inValid.length === 0) {
        this.toastService.showLoading();
        const endPoint = this.menu.api + '/' + this.activatedRoute.snapshot.paramMap.get('id');
        const queryParam: QueryParam = {};
        if (this.menu && this.menu.query) {
          queryParam.class = this.menu.query;
        }
        this.crudService.put(endPoint, dot.object(formData), queryParam)
        .subscribe((response) => {
          if (response.error && response.error.code === AppConst.SERVICE_STATUS.SUCCESS) {
              this.toastService.success(response.error.message);
              this._location.back();
          } else {
              this.toastService.error(response.error.message);
          }
            this.toastService.clearLoading();
        });
      } else {
        this.toastService.error(inValid.toString() + ' is required');
      }
    }

    cancel() {
      this._location.back();
    }

    redirect(url: string): void {
        this.router.navigate([ '/admin/actions/' + this.apiEndPoint + '/' + url]);
    }
    geocodePosition(pos) {
      let thiss = this;
      this.geocoder.geocode({
        latLng: pos
      }, function(responses) {
        if (responses && responses.length > 0) {
          let index: any;
          responses[0].address_components.forEach(element => {
              if (element.types.indexOf('locality') > -1) {
                index = thiss.menu.add.fields.findIndex((e) => (e.name === 'address.city'));
                if (index > -1) {
                  thiss.menu.add.fields[index]['value'] = element.long_name;
                }
              }
              if (element.types.indexOf('administrative_area_level_1') > -1) {
                index = thiss.menu.add.fields.findIndex((e) => (e.name === 'address.state'));
                if (index > -1) {
                  thiss.menu.add.fields[index]['value'] = element.long_name;
                }
              }
              if (element.types.indexOf('country') > -1) {
                index = thiss.menu.add.fields.findIndex((e) => (e.name === 'address.country'));
                if (index > -1) {
                  thiss.menu.add.fields[index]['value'] = element.long_name;
                }
              }
          });
          thiss.updateMarkerAddress(responses[0].formatted_address);
        } else {
          thiss.updateMarkerAddress('Cannot determine address at this location.');
        }
      });
    }
    updateMarkerPosition(latLng) {
      let index: any = this.menu.add.fields.findIndex((e) => (e.name === 'address.latitude'));
      if (index > -1) {
        this.menu.add.fields[index]['value'] = latLng.lat();
      }
      index = this.menu.add.fields.findIndex((e) => (e.name === 'address.longitude'));
      if (index > -1) {
        this.menu.add.fields[index]['value'] = latLng.lng();
      }
      index = this.menu.add.fields.findIndex((e) => (e.name === 'lat'));
      if (index > -1) {
        this.menu.add.fields[index]['value'] = latLng.lat();
      }
      index = this.menu.add.fields.findIndex((e) => (e.name === 'lon'));
      if (index > -1) {
        this.menu.add.fields[index]['value'] = latLng.lng();
      }
    }
    updateMarkerAddress(str) {
      const addressIndex: any = this.menu.add.fields.findIndex((e) => (e.name === 'address.addressline1'));
      this.menu.add.fields[addressIndex]['value'] = str;
      console.log('thiss.menu.add.fields', this.menu.add.fields);
    }
    initialize() {
        let thiss = this;
        this.populateMarker(24.578048697375753, -77.88542495468748);
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
  
