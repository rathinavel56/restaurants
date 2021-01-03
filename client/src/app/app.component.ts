import { Component, OnInit } from '@angular/core';
import { SessionService } from './api/services/session-service';

declare const $: any;

@Component({
    selector: 'app-root',
    templateUrl: './app.component.html',
    styleUrls: ['./app.component.scss']
})
export class AppComponent {
    constructor(
        private sessionService: SessionService
    ) {
        this.sessionService.isLogined();
    }

    ngOnInit(): void {
        $(document).ready(function () {
            setTimeout(function () {
                $(function () {
                    $('[data-toggle="tooltip"]').tooltip()
                });
            }, 500);
        });
    }
}
