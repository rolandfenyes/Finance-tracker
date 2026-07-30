/* eslint-disable @typescript-eslint/explicit-function-return-type */
import {
  Body,
  Controller,
  Delete,
  Get,
  HttpCode,
  Inject,
  Param,
  ParseUUIDPipe,
  Patch,
  Post,
  Put,
  Query,
  Req,
  UseGuards,
} from '@nestjs/common';
import {
  ApiCookieAuth,
  ApiCreatedResponse,
  ApiNoContentResponse,
  ApiOkResponse,
  ApiOperation,
  ApiTags,
} from '@nestjs/swagger';
import type { Request } from 'express';
import { AdminGuard } from '../administration/admin.guard';
import { AuthenticationGuard, VerifiedEmailGuard } from '../identity/authentication.guard';
import {
  AssignSubscriptionDto,
  BillingPageQueryDto,
  BillingResponseDto,
  PaymentDto,
  PlanDto,
  PromotionDto,
  TrialPromotionDto,
  UpdateInvoiceDto,
} from './billing.dto';
import { BillingService } from './billing.service';

@ApiTags('Administrative billing')
@ApiCookieAuth()
@UseGuards(AuthenticationGuard, VerifiedEmailGuard, AdminGuard)
@Controller('admin')
export class BillingController {
  constructor(@Inject(BillingService) private readonly billing: BillingService) {}

  @Get('billing/summary')
  @ApiOperation({
    summary: 'Read administrative billing records; no provider operations are available',
  })
  @ApiOkResponse({ type: BillingResponseDto })
  summary(@Query() query: BillingPageQueryDto) {
    return this.billing.summary(query.limit);
  }

  @Get('billing/plans')
  @ApiOkResponse({ type: BillingResponseDto })
  plans() {
    return this.billing.plans();
  }
  @Get('billing/plans/:id')
  @ApiOkResponse({ type: BillingResponseDto })
  plan(@Param('id', new ParseUUIDPipe({ version: '4' })) id: string) {
    return this.billing.plan(id);
  }
  @Post('billing/plans')
  @ApiCreatedResponse({ type: BillingResponseDto })
  createPlan(@Body() dto: PlanDto, @Req() req: Request) {
    return this.billing.createPlan(adminId(req), dto);
  }
  @Patch('billing/plans/:id')
  @ApiOkResponse({ type: BillingResponseDto })
  updatePlan(
    @Param('id', new ParseUUIDPipe({ version: '4' })) id: string,
    @Body() dto: PlanDto,
    @Req() req: Request,
  ) {
    return this.billing.updatePlan(adminId(req), id, dto);
  }
  @Delete('billing/plans/:id')
  @HttpCode(204)
  @ApiNoContentResponse()
  deletePlan(@Param('id', new ParseUUIDPipe({ version: '4' })) id: string, @Req() req: Request) {
    return this.billing.deletePlan(adminId(req), id);
  }

  @Get('billing/promotions')
  @ApiOkResponse({ type: BillingResponseDto })
  promotions() {
    return this.billing.promotions();
  }
  @Get('billing/promotions/:id')
  @ApiOkResponse({ type: BillingResponseDto })
  promotion(@Param('id', new ParseUUIDPipe({ version: '4' })) id: string) {
    return this.billing.promotion(id);
  }
  @Post('billing/promotions')
  @ApiCreatedResponse({ type: BillingResponseDto })
  createPromotion(@Body() dto: PromotionDto, @Req() req: Request) {
    return this.billing.createPromotion(adminId(req), dto);
  }
  @Patch('billing/promotions/:id')
  @ApiOkResponse({ type: BillingResponseDto })
  updatePromotion(
    @Param('id', new ParseUUIDPipe({ version: '4' })) id: string,
    @Body() dto: PromotionDto,
    @Req() req: Request,
  ) {
    return this.billing.updatePromotion(adminId(req), id, dto);
  }
  @Delete('billing/promotions/:id')
  @HttpCode(204)
  @ApiNoContentResponse()
  deletePromotion(
    @Param('id', new ParseUUIDPipe({ version: '4' })) id: string,
    @Req() req: Request,
  ) {
    return this.billing.deletePromotion(adminId(req), id);
  }
  @Post('billing/promotions/trial')
  @ApiCreatedResponse({ type: BillingResponseDto })
  trial(@Body() dto: TrialPromotionDto, @Req() req: Request) {
    return this.billing.trialPromotion(adminId(req), dto);
  }

  @Put('users/:id/subscription')
  @ApiOkResponse({ type: BillingResponseDto })
  assign(
    @Param('id', new ParseUUIDPipe({ version: '4' })) id: string,
    @Body() dto: AssignSubscriptionDto,
    @Req() req: Request,
  ) {
    return this.billing.assign(adminId(req), id, dto);
  }
  @Patch('invoices/:id')
  @ApiOkResponse({ type: BillingResponseDto })
  invoice(
    @Param('id', new ParseUUIDPipe({ version: '4' })) id: string,
    @Body() dto: UpdateInvoiceDto,
    @Req() req: Request,
  ) {
    return this.billing.updateInvoice(adminId(req), id, dto);
  }
  @Post('payments')
  @ApiCreatedResponse({ type: BillingResponseDto })
  createPayment(@Body() dto: PaymentDto, @Req() req: Request) {
    return this.billing.createPayment(adminId(req), dto);
  }
  @Patch('payments/:id')
  @ApiOkResponse({ type: BillingResponseDto })
  updatePayment(
    @Param('id', new ParseUUIDPipe({ version: '4' })) id: string,
    @Body() dto: PaymentDto,
    @Req() req: Request,
  ) {
    return this.billing.updatePayment(adminId(req), id, dto);
  }
}

function adminId(request: Request): string {
  return request.session.principal!.userId;
}
