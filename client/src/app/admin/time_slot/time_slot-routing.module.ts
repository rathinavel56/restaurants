
import { NgModule } from '@angular/core';
import { Routes, RouterModule } from '@angular/router';
import { TimeSlotComponent } from './time_slot.component';

const routes: Routes = [
    {
        path: '',
        component: TimeSlotComponent
    }
];

@NgModule({
    imports: [RouterModule.forChild(routes)],
    exports: [RouterModule]
})
export class TimeSlotRoutingModule {}
