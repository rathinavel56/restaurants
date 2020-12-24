
import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { TimeSlotRoutingModule } from './time_slot-routing.module';
import { TimeSlotComponent } from './time_slot.component';
import { ReactiveFormsModule, FormsModule } from '@angular/forms';
@NgModule({
    imports: [
        CommonModule,
        FormsModule,
        ReactiveFormsModule,
        TimeSlotRoutingModule
    ],
    declarations: [TimeSlotComponent]
})
export class TimeSlotModule {}
