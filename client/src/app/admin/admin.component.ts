
import { Component, OnInit, EventEmitter, Output } from '@angular/core';

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

    ngOnInit() { }

    toggleCollapsed() {
        this.collapsed = !this.collapsed;
        this.collapsedEvent.emit(this.collapsed);
    }

    receiveCollapsed($event) {
        this.collapedSideBar = $event;
    }
}
