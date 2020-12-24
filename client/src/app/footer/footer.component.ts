
import { Component } from '@angular/core';
import { RouterModule, Router, NavigationEnd } from '@angular/router';
import { UserService } from '../api/services/user.service';
@Component({
    selector: 'app-footer',
    templateUrl: './footer.component.html',
    styleUrls: ['./footer.component.scss']
})
export class FooterComponent {
    public hideFooter = false;
    public pages: any;
    public footerRemove: string[] = ['/login', '/signup', '/forgot-password'];
    constructor(private router: Router,
        private userService: UserService) {
        this.router.events.subscribe((event) => {
            if (event instanceof NavigationEnd) {
                if (event.url.indexOf('/admin') === -1) {
                    this.hideFooter = !(this.footerRemove.indexOf(event.url) > -1);
                    this.getPages();
                }
            }
        });
    }

    getPages(): void {
        this.userService
            .getAllPages()
            .subscribe((response) => {
              if (response.data) {
                this.pages = response.data;
              }
            });
    }

    redirect(url: string): void {
        this.router.navigate([ url ]);
    }
}
