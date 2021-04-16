import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CrudComponent } from './crud.component';
import { CrudRoutingModule } from './crud-routing.module';
import { SharedCommonCrudModule } from '../common-shared/shared-common.crud.module';
import { NgbModule } from '@ng-bootstrap/ng-bootstrap';
@NgModule({
  declarations: [CrudComponent],
  imports: [
    CommonModule,
    CrudRoutingModule,
    SharedCommonCrudModule,
    NgbModule
  ]
})
export class CrudModule { }
