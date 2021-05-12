import { Component, OnInit, Input } from '@angular/core';
import { Router } from '@angular/router';
import { CrudService } from '../../../../api/services/crud.service';
import { ToastService } from '../../../../api/services/toast-service';
import { SessionService } from '../../../../api/services/session-service';
import { StartupService } from '../../../../api/services/startup.service';
import { AppConst } from '../../../../utils/app-const';
import { QueryParam } from '../../../../api/models/query-param';
import * as dot from 'dot-object';
import {Location} from '@angular/common';
import { TagsChangedEvent } from 'ngx-tags-input/public-api';
import { HttpClient } from '@angular/common/http';
import {NgbDate, NgbCalendar} from '@ng-bootstrap/ng-bootstrap';
declare const google: any;
@Component({
  selector: 'app-add',
  templateUrl: './add.component.html',
  styleUrls: ['./add.component.scss']
})
export class AddComponent implements OnInit {

  public apiEndPoint: string;
  public menu: any;
  public responseData: any;
  public settings: any;
  public hoveredDate: NgbDate | null = null;
  public fromDate: NgbDate;
  public toDate: NgbDate | null = null;
  public windowData: any = window;
  public isFirstTime: any = false;
  geocoder: any;
  constructor(private crudService: CrudService,
    private toastService: ToastService,
    private sessionService: SessionService,
    public startupService: StartupService,
    private _location: Location,
    public router: Router,
    private httpClient: HttpClient,
    calendar: NgbCalendar) {
      let thiss = this;
      this.windowData.top.addFunc = function (value) {
        if (!thiss.isFirstTime) {
          setTimeout(() => {
            thiss.menuItem(value);
            thiss.isFirstTime = true;
          }, 500);
        } else {
          thiss.menuItem(value);
        }
      };
      this.fromDate = calendar.getToday();
      this.toDate = calendar.getNext(calendar.getToday(), 'd', 10);
      this.geocoder = new google.maps.Geocoder();
      
      setTimeout(function () {
          google.maps.event.addDomListener(window, 'load', this.initialize);
          thiss.populateMarker(24.578048697375753, -77.88542495468748);
      }, 2000);
  }

  ngOnInit(): void {
    
  }
    
  menuItem(value: any) {
    if (value) {
      this.menu = value;
      this.menu.add.fields.forEach((element, index) => {
        if (element.type === 'tags' || element.type === 'searchable') {
          this.crudService.get(element.reference, null)
          .subscribe((response) => {
            element.options = response.data;
          });
          element.value = [];
        } else if (element.type === 'select') {
          if (element.reference) {
                let query = null;
                if (element.query) {
                  query = {
                    class: element.query
                  };
                }
                this.crudService.get(element.reference, query)
                  .subscribe((responseRef) => {
                    element.options = responseRef.data;
                    element.value = element.options[0];
                  });
          } else if (element.option_values) {
            element.options = element.option_values.split(',');
          }
        } else {
          element.value = '';
        }
      });
    }
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

  cancel() {
    this._location.back();
  }

  changeSelect(item, event, setOption) {
    if (setOption) {
      item.value = setOption.find((e) =>{
        e.setOption.id == event.target.value; 
      });
    } else {
      item.value = event.target.value;
    }
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
      } else {
          this.toastService.error(response.error.message);
      }
        this.toastService.clearLoading();
    });
  }

  onDateSelection(date: NgbDate) {
    if (!this.fromDate && !this.toDate) {
      this.fromDate = date;
    } else if (this.fromDate && !this.toDate && date.after(this.fromDate)) {
      this.toDate = date;
    } else {
      this.toDate = null;
      this.fromDate = date;
    }
  }

  isHovered(date: NgbDate) {
    return this.fromDate && !this.toDate && this.hoveredDate && date.after(this.fromDate) && date.before(this.hoveredDate);
  }

  isInside(date: NgbDate) {
    return this.toDate && date.after(this.fromDate) && date.before(this.toDate);
  }

  isRange(date: NgbDate) {
    return date.equals(this.fromDate) || (this.toDate && date.equals(this.toDate)) || this.isInside(date) || this.isHovered(date);
  }

  changeDropDown(event, item) {
    if (item.is_dependent) {
      let query = null;
      if (item.query) {
        query = {
          class: null
        };
      }
      this.crudService.get(item.dependent_api, query)
        .subscribe((responseRef) => {
          if (responseRef.data) {
            this.menu.add.fields[item.child_drop].options = responseRef.data;
          } else {
            this.menu.add.fields[item.child_drop].options = [];
          }
          this.menu.add.fields[item.child_drop].value = [];
        });
    }
  }

  submit() {
    const inValid = [];
    const formData = {};
    const reg = /^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,4})$/;
    this.menu.add.fields.forEach((element, index) => {
      if (element.is_required && element.is_required === true && ((Array.isArray(element.value)
      && element.value.length === 0) || (!Array.isArray(element.value) && element.value.toString().trim() === ''))) {
        inValid.push(element.label);
      } else if (element.name === 'email' && reg.test(element.value) === false) {
        inValid.push('Enter the valid email');
      } else if (element.type === 'date') {
        formData[element.name] = element.value.year + '-' + (element.value.month.toString().length == 1 ? ('0' + element.value.month) : element.value.month) + '-' + (element.value.day.toString().length == 1 ? ('0' + element.value.day) : element.value.day);
      } else {
        formData[element.name] = (element.type === 'file') ? element.file : element.value;
      }
    });
    if (inValid.length === 0) {
      this.toastService.showLoading();
      const queryParam: QueryParam = {};
      if (this.menu && this.menu.query) {
        queryParam.class = this.menu.query;
      }
      this.crudService.post(this.menu.api, dot.object(formData), queryParam)
      .subscribe((response) => {
        if (response.error && response.error.code === AppConst.SERVICE_STATUS.SUCCESS) {
            this.toastService.success(response.error.message);
            // this.router.navigate([this.menu.route]);
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
  }
  initialize() {
      let thiss = this;
      this.populateMarker(24.578048697375753, -77.88542495468748);
  }
  populateMarker(lat, lon) {
    if (document.getElementById('mapCanvas')) {
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
}
