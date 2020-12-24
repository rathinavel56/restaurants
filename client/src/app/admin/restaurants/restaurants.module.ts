
import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RestaurantRoutingModule } from './restaurants-routing.module';
import { RestaurantComponent } from './restaurants.component';
import { ReactiveFormsModule, FormsModule } from '@angular/forms';
import { GooglePlaceModule } from "ngx-google-places-autocomplete";
@NgModule({
    imports: [
        CommonModule,
        FormsModule,
        ReactiveFormsModule,
        RestaurantRoutingModule,
        GooglePlaceModule,
        FormsModule
    ],
    declarations: [RestaurantComponent]
})
export class RestaurantModule {}