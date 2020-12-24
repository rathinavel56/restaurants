
import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CustomTimeSlotRoutingModule } from './custom_time_slot-routing.module';
import { CustomTimeSlotComponent } from './custom_time_slot.component';
import { ReactiveFormsModule, FormsModule } from '@angular/forms';
import { NgbModule } from '@ng-bootstrap/ng-bootstrap';

@NgModule({
    imports: [
        CommonModule,
        FormsModule,
        ReactiveFormsModule,
        CustomTimeSlotRoutingModule,
        NgbModule
    ],
    declarations: [
        CustomTimeSlotComponent
    ]
})
export class CustomTimeSlotModule {}
