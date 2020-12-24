
import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { SessionService } from '../api/services/session-service';
import { StartupService } from '../api/services/startup.service';
import { ToastService } from '../api/services/toast-service';
@Component({
    selector: 'app-home',
    templateUrl: './home.component.html',
    styleUrls: ['./home.component.scss']
})
export class HomeComponent implements OnInit {
    public settings: any;
    constructor(public sessionService: SessionService,
        public startupService: StartupService,
        private toastService: ToastService,
        private router: Router) {}

    ngOnInit() {
        this.settings = this.startupService.startupData();
        if (this.router.url.indexOf('success') > -1) {
            this.toastService.success('Payment completed Successfully');
        }
        if (this.router.url.indexOf('pending') > -1) {
            this.toastService.error('Payment pending');
        }
        if (this.router.url.indexOf('fail') > -1) {
            this.toastService.error('Payment completed failed');
        }
    }
}
