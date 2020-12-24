
import { NgModule } from '@angular/core';
import { Routes, RouterModule } from '@angular/router';
import { CustomTimeSlotComponent } from './custom_time_slot.component';

const routes: Routes = [
    {
        path: '',
        component: CustomTimeSlotComponent
    }
];

@NgModule({
    imports: [RouterModule.forChild(routes)],
    exports: [RouterModule]
})
export class CustomTimeSlotRoutingModule {}
