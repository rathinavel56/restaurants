
import { NgModule } from '@angular/core';
import { Routes, RouterModule } from '@angular/router';
import { AdminComponent } from './admin.component';

const routes: Routes = [
    {
        path: '',
        component: AdminComponent,
        children: [
            {
                path: '', redirectTo: 'admin/brands', pathMatch: 'prefix'
            },
            // {
            //     path: 'dashboard',
            //     loadChildren: () =>
            //         import('./dashboard/dashboard.module').then(
            //             (m) => m.DashboardModule
            //         )
            // },
            {
                path: 'change_password',
                loadChildren: () =>
                    import('./passwordchanged/passwordchanged.module').then(
                        (m) => m.PasswordchangedModule
                    )
            },
            {
                path: 'restaurants',
                loadChildren: () =>
                    import('./restaurants/restaurants.module').then(
                        (m) => m.RestaurantModule
                    )
            },{
                path: 'time_slot',
                loadChildren: () =>
                    import('./time_slot/time_slot.module').then(
                        (m) => m.TimeSlotModule
                    )
            },
            {
                path: 'custom_time_slot',
                loadChildren: () =>
                    import('./custom_time_slot/custom_time_slot.module').then(
                        (m) => m.CustomTimeSlotModule
                    )
            },
            {
                path: 'actions/:api',
                loadChildren: () =>
                    import('./crud/crud.module').then((m) => m.CrudModule)
            },
            {
                path: 'actions/:api/add',
                loadChildren: () =>
                    import('./crud/crud.module').then((m) => m.CrudModule)
            },
            {
                path: 'actions/:api/edit/:id',
                loadChildren: () =>
                    import('./crud/crud.module').then((m) => m.CrudModule)
            },
            {
                path: 'actions/:api/view/:id',
                loadChildren: () =>
                    import('./crud/crud.module').then((m) => m.CrudModule)
            },
            {
                path: 'actions/:api/delete/:id',
                loadChildren: () =>
                    import('./crud/crud.module').then((m) => m.CrudModule)
            }
        ]
    }
];

@NgModule({
    imports: [RouterModule.forChild(routes)],
    exports: [RouterModule]
})
export class AdminRoutingModule {}
