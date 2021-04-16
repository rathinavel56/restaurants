
import { Component, OnInit } from '@angular/core';
import { routerTransition } from '../../router.animations';
import { ToastService } from '../../api/services/toast-service';
import { UserService } from '../../api/services/user.service';
import { NgbDateStruct, NgbCalendar } from '@ng-bootstrap/ng-bootstrap';

@Component({
    selector: 'app-custom-time-slot',
    templateUrl: './custom_time_slot.component.html',
    styleUrls: ['./custom_time_slot.component.scss'],
    animations: [routerTransition()]
})
export class CustomTimeSlotComponent implements OnInit {
    timeSlots: any = [];
    schedule: any = {};
    customDate: NgbDateStruct;
    currentFullDate = new Date();
    discounts: any = ['', 5,10,15,20,25,30,35,40,45,50,55,60,65,70]
    sessionService: any;
    restaurant_id: any;
    restaurants: any = [];

    constructor(private toastService: ToastService,
        private userService: UserService,
        private calendar: NgbCalendar) {
            this.customDate = this.calendar.getToday();
    }

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
        this.schedule = {
            customDate: this.dateToString(this.customDate),
            type: 0,
            timeSlots: JSON.parse(JSON.stringify(this.timeSlots))
        };
        if (this.sessionService.role_id === 4) {
            this.restaurantList();
        } else {
            this.customTimeSlotDetails();
        }
    }
    customTimeSlotDetails() {
        this.toastService.showLoading();
        this.userService.customTimeSlotDetails({
            date_detail: this.dateToString(this.customDate),
            restaurant_id: (this.sessionService.role_id === 4) ? this.restaurant_id : ''
        }).subscribe((response) => {
            if (response.data && response.data.length > 0) {
                let savedData = response.data.find((e) => (e.day === this.schedule.day));
                if (savedData) {
                    this.schedule.type = savedData.type;
                    if (savedData.type === 0) {
                        this.schedule.timeSlots.forEach(slot => {
                            let savedSlot = savedData.slots.find((s) => (s.from_timeslot === slot.time));
                            if (savedSlot) {
                                slot.slot = savedSlot.slot_count;
                            }
                        });
                    }
                }
            } else {
                this.schedule.timeSlots = JSON.parse(JSON.stringify(this.timeSlots));
                this.schedule.type = 0;
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
        let day = JSON.parse(JSON.stringify(this.schedule));
        let selectedDay = {};
        let addSlots = day.timeSlots.filter((e) => e.slot > 0);
        if (addSlots.length > 0 || day.type === 1) {
            selectedDay = {
                type: day.type,
                time_slots: addSlots,
                date_detail: this.dateToString(this.customDate),
                restaurant_id: (this.sessionService.role_id === 4) ? +this.restaurant_id : ''
            };
        }
        if (selectedDay) {
            this.toastService.showLoading();
            this.userService.customTimeSlot(selectedDay).subscribe((response) => {
                this.toastService.success(response.error.message);
                this.toastService.clearLoading();
            });
            return;
        }
        this.toastService.error('Please choose atleast one day');
    }
    dateToString(dateDetail) {
        return (dateDetail.year + '-' + (dateDetail.month.length === 1 ? ('0' + dateDetail.month) : dateDetail.month) + '-' + (dateDetail.day.length === 1 ? ('0' + dateDetail.day) : dateDetail.day));
    }
    restaurantList() {
        this.toastService.showLoading();
        this.userService.restaurantList().subscribe((response) => {
            if (response.data && response.data.length > 0) {
                this.restaurants = response.data;
                this.restaurant_id = this.restaurants[0].id;
            }
            this.customTimeSlotDetails();
        });
    }
}