
import { NgModule } from '@angular/core';
import { Routes, RouterModule } from '@angular/router';
import { RestaurantComponent } from './restaurants.component';

const routes: Routes = [
    {
        path: '',
        component: RestaurantComponent
    }
];

@NgModule({
    imports: [RouterModule.forChild(routes)],
    exports: [RouterModule]
})
export class RestaurantRoutingModule {}
