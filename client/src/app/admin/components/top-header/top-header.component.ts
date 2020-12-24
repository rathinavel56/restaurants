import { Component, OnInit } from '@angular/core';
import { Router, NavigationEnd } from '@angular/router';
@Component({
  selector: 'app-top-header',
  templateUrl: './top-header.component.html',
  styleUrls: ['./top-header.component.css']
})
export class TopHeaderComponent implements OnInit {
  currentUrl: any;
  constructor(public router: Router) { 
    this.router.events.subscribe((val) => {
      this.setCurrentUrl();
    });
  }

  ngOnInit(): void {
    this.setCurrentUrl();
  }

  setCurrentUrl() {
    this.currentUrl = window.location.href;
    this.currentUrl = this.currentUrl.split('/');
    this.currentUrl = this.currentUrl[this.currentUrl.length-1];
    this.currentUrl = this.currentUrl.split('_');
    this.currentUrl = this.currentUrl.join(' ');
  }

}
