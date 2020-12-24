
import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ContactusRoutingModule } from './contactus-routing.module';
import { ContactusComponent } from './contactus.component';
import { PaymentService } from '../api/services/payment.service';
@NgModule({
    declarations: [ContactusComponent],
    imports: [CommonModule, ContactusRoutingModule],
    providers: [PaymentService]

})
export class ContactusModule {}
