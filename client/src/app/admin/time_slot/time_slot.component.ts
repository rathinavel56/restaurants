
import { Component, OnInit } from '@angular/core';
import { routerTransition } from '../../router.animations';
import { ToastService } from '../../api/services/toast-service';
import { UserService } from '../../api/services/user.service';
@Component({
    selector: 'app-time-slot',
    templateUrl: './time_slot.component.html',
    styleUrls: ['./time_slot.component.scss'],
    animations: [routerTransition()]
})
export class TimeSlotComponent implements OnInit {
    timeSlots: any = [];
    schedules: any = [];
    restaurants: any = [];
    discounts: any = ['', 5,10,15,20,25,30,35,40,45,50,55,60,65,70]
    sessionService: any;
    restaurant_id: any;

    constructor(private toastService: ToastService,
        private userService: UserService) {}

    ngOnInit() {
        this.sessionService = JSON.parse(sessionStorage.getItem('user_context'));
        for (let i = 0; i <= 23; i++) {
            let timeValue = (i.toString().length === 1) ? ('0' + i) : i;
            this.timeSlots.push({
                time: timeValue + ':00',
                slot: ''
            });
            this.timeSlots.push({
                time: timeValue + ':30',
                slot: ''
            });
        }
        this.schedules = [{
            day: 'Sun',
            type: 0,
            timeSlots: JSON.parse(JSON.stringify(this.timeSlots))
        },{
            day: 'Mon',
            type: 0,
            timeSlots: JSON.parse(JSON.stringify(this.timeSlots))
        },{
            day: 'Tue',
            type: 0,
            timeSlots: JSON.parse(JSON.stringify(this.timeSlots))
        },{
            day: 'Wed',
            type: 0,
            timeSlots: JSON.parse(JSON.stringify(this.timeSlots))
        },{
            day: 'Thu',
            type: 0,
            timeSlots: JSON.parse(JSON.stringify(this.timeSlots))
        },{
            day: 'Fri',
            type: 0,
            timeSlots: JSON.parse(JSON.stringify(this.timeSlots))
        },{
            day: 'Sat',
            type: 0,
            timeSlots: JSON.parse(JSON.stringify(this.timeSlots))
        }];
        if (this.sessionService.role_id === 4) {
            this.restaurantList();
        } else {
            this.timeSlotDetails();
        }
    }
    timeSlotDetails() {
        this.toastService.showLoading();
        this.userService.timeSlotDetails({
            restaurant_id: (this.sessionService.role_id === 4) ? this.restaurant_id : ''
        }).subscribe((response) => {
            if (response.data && response.data.length > 0) {
                this.schedules.forEach(element => {
                    let savedData = response.data.find((e) => (e.day === element.day));
                    if (savedData) {
                        element.type = savedData.type;
                        if (savedData.type === 0) {
                            element.timeSlots.forEach(slot => {
                                let savedSlot = savedData.slots.find((s) => (s.from_timeslot === slot.time));
                                if (savedSlot) {
                                    slot.slot = savedSlot.slot_count;
                                }
                            });
                        }
                    }
                });
            }
            this.toastService.clearLoading();
        });
    }
    updateSchedule(schedule) {
        schedule.type = (schedule.type) === 0 ? 1 : 0; 
    }
    updatetimeSlot(timeSlot, event) {
        timeSlot.slot = +event.target.value;
    }
    onSubmit() {
        let days = JSON.parse(JSON.stringify(this.schedules));
        if (days.length > 0) {
            let selectedDays = [];
            days.forEach(element => {
                let addSlots = element.timeSlots.filter((e) => e.slot > 0);
                if (addSlots.length > 0 || element.type === 1) {
                    selectedDays.push({
                        day: element.day,
                        type: element.type,
                        timeSlots: addSlots
                    });
                }                
            });
            if (selectedDays.length > 0) {
                this.toastService.showLoading();
                this.userService.timeSlot({
                    time_slot: selectedDays,
                    restaurant_id: (this.sessionService.role_id === 4) ? +this.restaurant_id : ''
                }).subscribe((response) => {
                    this.toastService.success(response.error.message);
                    this.toastService.clearLoading();
                });
                return;
            }
        }
        this.toastService.error('Please choose atleast one day');
    }

    restaurantList() {
        this.toastService.showLoading();
        this.userService.restaurantList().subscribe((response) => {
            if (response.data && response.data.length > 0) {
                this.restaurants = response.data;
                this.restaurant_id = this.restaurants[0].id;
            }
            this.timeSlotDetails();
        });
    }
}
