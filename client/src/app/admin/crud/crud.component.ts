import { element } from 'protractor';
import { Component } from '@angular/core';
import { ActivatedRoute, Router, Event, NavigationEnd } from '@angular/router';
import { StartupService } from '../../api/services/startup.service';
import { SessionService } from '../../api/services/session-service';
@Component({
  selector: 'app-crud',
  templateUrl: './crud.component.html',
  styleUrls: ['./crud.component.scss']
})
export class CrudComponent {
  public apiEndPoint: string;
  public settings: any;
  public menu: string;
  public id: number;
  public list: boolean;
  public add: boolean;
  public edit: boolean;
  public view: boolean;
  public windowData: any = window;
  public isFirstTime: any = false;
  constructor(private activatedRoute: ActivatedRoute,
    public startupService: StartupService,
    private sessionService: SessionService,
    protected router: Router) {
      this.router.events.subscribe((event: Event) => {
        if (event instanceof NavigationEnd) {
          this.setPage();
        }
    });
  }

  setPage() {
    this.apiEndPoint = '/' + this.activatedRoute.snapshot.paramMap.get('api');
    this.id = +this.activatedRoute.snapshot.paramMap.get('id');
    this.list = (!(this.windowData.location.href.indexOf('/add') > -1) && !(this.windowData.location.href.indexOf('/edit') > -1)
    && !(this.windowData.location.href.indexOf('/view') > -1));
    this.add = (this.windowData.location.href.indexOf('/add') > -1);
    this.edit = (this.windowData.location.href.indexOf('/edit') > -1);
    this.view = (this.windowData.location.href.indexOf('/view') > -1);
    this.setMenu();    
  }

  setMenu() {
    this.settings = this.startupService.startupData();
    const menus = this.settings.MENU;
    const session: any = JSON.parse(sessionStorage.getItem('user_context'));
    if (!menus[1].isFormat) {
        menus.forEach(formatMenu => {
        if (!formatMenu.role_id || formatMenu.role_id.indexOf(session.role_id) > -1) {
         if (!formatMenu.child_sub_menu && formatMenu.copyFields) {
            formatMenu.listview.fields = formatMenu.listview.fields.filter((x) => (x.list === true));
            formatMenu.add = {
              fields: formatMenu.listview.fields.filter((x) => (x.add === true))
            };
            formatMenu.edit = {
              fields: formatMenu.listview.fields.filter((x) => (x.edit === true))
            };
            formatMenu.view = {
              fields: formatMenu.listview.fields.filter((x) => (x.view === true))
            };
          } else if (!formatMenu.copyFields && !formatMenu.child_sub_menu && formatMenu.listview && formatMenu.listview.fields) {
            let feilds = formatMenu.listview.fields;
            formatMenu.listview.fields = feilds.filter((x) => (x.list === true));
            formatMenu.add = {
              fields: feilds.filter((x) => (x.add === true))
            };
            formatMenu.edit = {
              fields: feilds.filter((x) => (x.edit === true))
            };
            formatMenu.view = {
              fields: feilds.filter((x) => (x.view === true))
            };
          } else if (formatMenu.child_sub_menu) {
              formatMenu.child_sub_menu.forEach((childMenu, indexChild, objectData) => {
                if (!childMenu.role_id || childMenu.role_id.indexOf(session.role_id) > -1) {
                  this.addChildMenus(formatMenu, childMenu);
                } else {
                  objectData.splice(indexChild, 1);
                }
              });
          } else {
            this.addParentMenus(formatMenu, menus);
          }
        }
      });
      menus[1].isFormat = true;
      this.settings.MENU = menus;
      this.startupService.setStartupData(this.settings);
    }
    const apiService = '/admin/actions' + this.apiEndPoint;
    menus.forEach(menuItem => {
      if (menuItem.route === apiService) {
        this.menu = menuItem;
        if (!this.isFirstTime) {
          setTimeout(() => {
              this.setListMenuItem();
              this.isFirstTime = true;
          }, 1000);          
        } else {
          this.setListMenuItem();
        }
        
      }
      if (menuItem.child_sub_menu) {
        menuItem.child_sub_menu.forEach(childMenuItem => {
          if (!childMenuItem.role_id || childMenuItem.role_id.indexOf(session.role_id) > -1) {
            let childRoute = childMenuItem.route.split('/');
            let apiS = apiService.split('/');
            if (childRoute[childRoute.length-1].toLowerCase() === apiS[apiS.length-1].toLowerCase()) {
              this.menu = childMenuItem;
              setTimeout(() => {
                this.setListMenuItem();
              }, 500);
            }
          }
        });
      }
    });
  }

  setListMenuItem() {
    if (this.list) {
      this.windowData.top.listFunc(this.menu);
    }
    if (this.add) {
      this.windowData.top.addFunc(this.menu);
    }
    if (this.edit) {
      this.windowData.top.editFunc(this.menu);
    }
    if (this.view) {
      this.windowData.top.viewFunc(this.menu);
    }
  }

  addChildMenus(formatMenu: any, elementData: any) {
    if (elementData.listview || formatMenu.listview) {
      let listFields = formatMenu.listview.fields;
      if (elementData.listview) {        
          listFields = [...formatMenu.listview.fields, ...elementData.listview.fields];
          elementData.listview.fields = listFields;
      } else {
        elementData.listview = {
          fields: formatMenu.listview.fields.filter((x) => (x.list === true))
        };
      }
      const add = listFields.filter((x) => (x.add === true));
      if (add.length > 0) {
        elementData.add = {
          fields: add
        };
      }
      const edit = listFields.filter((x) => (x.edit === true));
      if (edit.length > 0) {
        elementData.edit = {
          fields: edit
        };
      }
      const view = listFields.filter((x) => (x.view === true));
      if (view.length > 0) {
        elementData.view = {
          fields: view
        };
      }
    }
  }

  addParentMenus(formatMenu: any, menus: any) {
    if (formatMenu.title === 'Companies' || formatMenu.title === 'Contestants') {
      formatMenu.api = menus[1].api;
      const listFields = (formatMenu.listview && formatMenu.listview.fields && formatMenu.listview.fields.length > 0 ) ?
      [...formatMenu.listview.fields, ...menus[1].listview.fields] : menus[1].listview.fields;
      formatMenu.listview = {
        fields: listFields
      };
      const addFields = (formatMenu.add && formatMenu.add.fields && formatMenu.add.fields.length > 0 ) ?
      [...formatMenu.add.fields, ...menus[1].add.fields] :
      ((menus[1].add && menus[1].add.fields && menus[1].add.fields.length > 0) ? menus[1].add.fields : []);
      formatMenu.add = {
        fields: addFields,
        url: menus[1].add.url
      };
      const editFields = (formatMenu.edit && formatMenu.edit.fields && formatMenu.edit.fields.length > 0 ) ?
      [...formatMenu.edit.fields, ...menus[1].edit.fields] :
      ((menus[1].edit && menus[1].edit.fields && menus[1].edit.fields.length > 0) ? menus[1].edit.fields : []);
      formatMenu.edit = {
        fields: editFields
      };
      const viewFields = (formatMenu.view && formatMenu.view.fields && formatMenu.view.fields.length > 0 ) ?
      [...formatMenu.view.fields, ...menus[1].view.fields] :
      ((menus[1].view && menus[1].view.fields && menus[1].view.fields.length > 0) ? menus[1].view.fields : []);
      formatMenu.view = {
        fields: viewFields
      };
    } else if (formatMenu.listview && formatMenu.listview.fields && formatMenu.listview.fields.length > 0) {
      const listviewFields = formatMenu.listview.fields;
      if (listviewFields.length > 0) {
        formatMenu.listview.fields = listviewFields.filter((x) => (x.list === true));
        const addField = listviewFields.filter((x) => (x.add === true));
        if (addField.length > 0) {
          formatMenu.add = {
            fields: addField
          };
        }
        const editField = listviewFields.filter((x) => (x.edit === true));
        if (editField.length > 0) {
          formatMenu.edit = {
            fields: editField
          };
        }
        const viewField = listviewFields.filter((x) => (x.view === true));
        if (viewField.length > 0) {
          formatMenu.view = {
            fields: viewField
          };
        }
      }
    }
  }
}
