
import { Component, HostListener, Inject } from '@angular/core';
import { RouterModule, Router, NavigationEnd } from '@angular/router';
import { SessionService } from 'src/app/api/services/session-service';
import { UserService } from 'src/app/api/services/user.service';
import { DOCUMENT } from '@angular/common';
import { AppConst } from 'src/app/utils/app-const';
import { ToastService } from 'src/app/api/services/toast-service';
@Component({
    selector: 'app-header',
    templateUrl: './header.component.html',
    styleUrls: ['./header.component.scss']
})
export class HeaderComponent {
    public hideHeader = false;
    public showStaticHeader = false;
    public isShow = false;
    public isScrollheader = false;
    public headerRemove: string[] = [
        '/login',
        '/signup',
        '/forgot-password',
        '/resend-link',
        '/reset-password',
        '/password-changed',
        '/admin',
        '/change-password'
    ];
    public staticheader: string[] = [
        '/profile',
        '/view-profile',
        '/cart',
        '/checkout',
        '/edit'
    ];
    constructor(
        @Inject(DOCUMENT) private document: Document,
        private router: Router,
        private userService: UserService,
        public sessionService: SessionService,
        private toastService: ToastService
    ) {
        this.router.events.subscribe((event) => {
            if (event instanceof NavigationEnd) {
                if (event.url.indexOf('/admin') === -1) {
                    this.hideHeader = !(this.headerRemove.indexOf(event.url) > -1) && !event.url.includes('/login');
                    this.showStaticHeader =
                        this.staticheader.indexOf(event.url) > -1;
                }
            }
        });
    }

    @HostListener('window:scroll', [])
    onWindowScroll() {
        this.isScrollheader =
            document.body.scrollTop > 367 ||
            document.documentElement.scrollTop > 367;
    }

    toggleSidebar(): void {
        this.isShow = !this.isShow;
    }

    redirect(url: string, id: string): void {
        this.router.navigate([url + id]);
    }

    logout(): void {
        this.userService.logout().subscribe((response) => {
            if (
                response.error &&
                response.error.code === AppConst.SERVICE_STATUS.SUCCESS
            ) {
                sessionStorage.removeItem('user_context');
                sessionStorage.removeItem('login_time');
                sessionStorage.removeItem('access_token');
                sessionStorage.removeItem('refresh_token');
                this.router.navigate(['/']);
                setTimeout(() => {
                    location.reload();
                }, 100);
            } else {
                this.toastService.error(response.error.message);
            }
            this.toastService.clearLoading();
        });
    }
}
