
import { AdminModule } from './admin.module';

describe('AdminModule', () => {
    let adminModule: AdminModule;

    beforeEach(() => {
        adminModule = new adminModule();
    });

    it('should create an instance', () => {
        expect(adminModule).toBeTruthy();
    });
});
