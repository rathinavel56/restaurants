
import { Component, OnInit, EventEmitter, Output } from '@angular/core';
declare const $: any;

@Component({
    selector: 'app-admin',
    templateUrl: './admin.component.html',
    styleUrls: ['./admin.component.scss']
})
export class AdminComponent implements OnInit {
    collapedSideBar: boolean;
    collapsed: boolean;
    isActive: boolean;
    @Output() collapsedEvent = new EventEmitter<boolean>();

    constructor() { }

    ngOnInit() {
        $(document).ready(function () {
            setTimeout(function () {
                $(function () {
                    $('[data-toggle="tooltip"]').tooltip()
                });
            }, 500);
        });
    }

    toggleCollapsed() {
        this.collapsed = !this.collapsed;
        this.collapsedEvent.emit(this.collapsed);
    }

    receiveCollapsed($event) {
        this.collapedSideBar = $event;
    }
}
