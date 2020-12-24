import { NgModule } from '@angular/core';
import { Routes, RouterModule } from '@angular/router';
import { AppComponent } from './app.component';
import { AuthGuard } from './shared';

const routes: Routes = [
    {
        path: '',
        loadChildren: () =>
            import('./login/login.module').then((m) => m.LoginModule)
    },
    {
        path: 'admin',
        loadChildren: () =>
            import('./admin/admin.module').then((m) => m.AdminModule),
        canActivate: [AuthGuard]
    },
    {
        path: 'page/:type',
        loadChildren: () =>
            import('./page/page.module').then((m) => m.PageModule)
    },
    {
        path: 'home',
        loadChildren: () =>
            import('./home/home.module').then((m) => m.HomeModule)
    },
    {
        path: 'login',
        loadChildren: () =>
            import('./login/login.module').then((m) => m.LoginModule)
    },
    {
        path: 'transaction',
        loadChildren: () =>
            import('./transaction/transaction.module').then(
                (m) => m.TransactionModule
            )
    },
    {
        path: 'contactus',
        loadChildren: () =>
            import('./contactus/contactus.module').then(
                (m) => m.ContactusModule
            )
    },
    {
        path: 'error',
        loadChildren: () =>
            import('./server-error/server-error.module').then(
                (m) => m.ServerErrorModule
            )
    },
    {
        path: 'access-denied',
        loadChildren: () =>
            import('./access-denied/access-denied.module').then(
                (m) => m.AccessDeniedModule
            )
    },
    {
        path: 'not-found',
        loadChildren: () =>
            import('./not-found/not-found.module').then((m) => m.NotFoundModule)
    },
    {path: '**', redirectTo: 'not-found'}
];

@NgModule({
    imports: [RouterModule.forRoot(routes)],
    exports: [RouterModule]
})
export class AppRoutingModule {}
