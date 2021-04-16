import { Component, OnInit, Input } from '@angular/core';
import { Router } from '@angular/router';
import { CrudService } from '../../../../api/services/crud.service';
import { ToastService } from '../../../../api/services/toast-service';
import { SessionService } from '../../../../api/services/session-service';
import { QueryParam } from '../../../../api/models/query-param';
import { AppConst } from '../../../../utils/app-const';
import * as dot from 'dot-object';
import Swal from 'sweetalert2';
@Component({
  selector: 'app-list',
  templateUrl: './list.component.html',
  styleUrls: ['./list.component.scss']
})
export class ListComponent implements OnInit {
  public menu: any;
  public responseData: any;
  public settings: any;
  public windowData: any = window;
  public isFirstTime: any = false;
  public metaData: any;
  public page = 1;
  public previousPage: any;
  search: any = '';

  constructor(private crudService: CrudService,
    private toastService: ToastService,
    public sessionService: SessionService,
    public router: Router) {
      let thiss = this;
      this.windowData.top.listFunc = function (value) {
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
      
  }
    
  menuItem(value: any) {
    if (value) {
      this.menu = value;
      this.search = '';
      this.menu.listview.fields = value.listview.fields.filter((x) => (x.list === true));
      if (this.menu && this.menu.api) {
        this.getRecords();
      }
    }
  }
  getFilterRecords() {
    this.previousPage = '';
    this.getRecords();
  }
  getRecords() {
    this.toastService.showLoading();
      const queryParam: QueryParam = {};
      if (this.menu && this.menu.query) {
        queryParam.class = this.menu.query;
      }
      if (this.previousPage) {
        queryParam.page = this.previousPage;
      }
      if (this.search) {
        queryParam.q = this.search;
      }
      this.crudService.get(this.menu.api, queryParam)
      .subscribe((responseApi) => {
          this.responseData = responseApi.data;
          this.metaData = responseApi._metadata;
          this.toastService.clearLoading();
      });
  }

  getValue(name: any, obj: any) {
    return (name && name !== 'actions') ? dot.pick(name, obj) : '';
  }

  redirect(url: string): void {
    this.router.navigate([ this.menu.route + '/' + url ]);
  }

  approveAll(url: string): void {
    this.approve(null);
  }

  disapproveAll(url: string): void {
    this.approve(null);
  }

  approve(id: string): void {
    Swal.fire({
      title: 'Are you sure?',
      text: 'You want to approve this!',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Yes, approve it!'
    }).then((result) => {
      if (result.value) {
        const queryParam: QueryParam = {
          class: 'approve'
        };
        const endPoint = (id !== null) ? ('/admin/approvals/' + id) : '/admin/approvals';
        this.crudService.put(endPoint, null, queryParam)
        .subscribe((response) => {
          if (response.error && response.error.code === AppConst.SERVICE_STATUS.SUCCESS) {
              this.toastService.success(response.error.message);
              this.getRecords();
          } else {
              this.toastService.error(response.error.message);
          }
        });
      }
    });
  }

  disapprove(id: string): void {
    Swal.fire({
      title: 'Are you sure?',
      text: 'You won\'t be able to revert this!',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
      if (result.value) {
        const queryParam: QueryParam = {
          class: 'disapprove'
        };
        const endPoint = (id !== null) ? ('/admin/approvals/' + id) : '/admin/approvals';
        this.crudService.put(endPoint, null, queryParam)
        .subscribe((response) => {
          if (response.error && response.error.code === AppConst.SERVICE_STATUS.SUCCESS) {
              this.toastService.success(response.error.message);
              this.getRecords();
          } else {
              this.toastService.error(response.error.message);
          }
        });
      }
    });
  }

  action(element: any, mode: string): void {
    if (mode.toLowerCase() === 'delete') {
      this.alertWarning(element['id']);
    } else if (mode.toLowerCase() === 'approve') {
      this.approve(element['id']);
    } else if (mode.toLowerCase() === 'disapprove') {
      this.disapprove(element['id']);
    } else {
      const url = this.menu.title.replace(/\s/g, '_').toLowerCase();
      this.router.navigate([ '/admin/actions/' + url + '/' + mode.toLowerCase() + '/' + element['id']]);
    }
  }

  alertWarning(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: 'You won\'t be able to revert this!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
      }).then((result) => {
        if (result.value) {
          const endPoint = this.menu.api + '/delete/' + id;
          this.crudService.put(endPoint, null, null)
          .subscribe((response) => {
            if (response.error && response.error.code === AppConst.SERVICE_STATUS.SUCCESS) {
                this.toastService.success(response.error.message);
                this.getRecords();
            } else {
                this.toastService.error(response.error.message);
            }
          });
        }
      });
  }

  loadPage(page: number) {
    if (page !== this.previousPage) {
        this.previousPage = page;
        this.getRecords();
    }
  }

}
