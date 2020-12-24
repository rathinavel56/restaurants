
import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { DashboardRoutingModule } from './dashboard-routing.module';
import { DashboardComponent } from './dashboard.component';
import { NotificationComponent } from './components';
import { StatModule } from '../../shared';
import { SharedCommonChartModule } from '../../shared-common/shared-common.chart.module';
@NgModule({
    imports: [
        CommonModule,
        DashboardRoutingModule,
        SharedCommonChartModule,
        StatModule
    ],
    declarations: [DashboardComponent, NotificationComponent]
})
export class DashboardModule {}
