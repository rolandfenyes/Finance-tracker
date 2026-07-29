import {
  Body,
  Controller,
  Delete,
  Get,
  HttpCode,
  Inject,
  Param,
  Post,
  Put,
  Req,
  UseGuards,
} from '@nestjs/common';
import {
  ApiBody,
  ApiCookieAuth,
  ApiCreatedResponse,
  ApiNoContentResponse,
  ApiOkResponse,
  ApiOperation,
  ApiTags,
} from '@nestjs/swagger';
import type { Request } from 'express';
import { AuthenticationGuard, VerifiedEmailGuard } from '../identity/authentication.guard';
import { PersonalFinanceAccessGuard } from '../users/entitlements.service';
import {
  AddUserCurrencyDto,
  CurrencyCatalogueResponseDto,
  SetMainCurrencyDto,
  UserCurrenciesResponseDto,
} from './currency.dto';
import { CurrencyService } from './currency.service';

@ApiTags('Currencies')
@ApiCookieAuth()
@UseGuards(AuthenticationGuard, VerifiedEmailGuard, PersonalFinanceAccessGuard)
@Controller()
export class CurrencyController {
  constructor(@Inject(CurrencyService) private readonly currencies: CurrencyService) {}

  @Get('currencies')
  @ApiOperation({ summary: 'List supported currencies and exact rounding metadata' })
  @ApiOkResponse({ type: CurrencyCatalogueResponseDto })
  catalogue(): Promise<CurrencyCatalogueResponseDto> {
    return this.currencies.catalogue();
  }

  @Get('users/me/currencies')
  @ApiOperation({ summary: 'List the current user currency memberships and main currency' })
  @ApiOkResponse({ type: UserCurrenciesResponseDto })
  userCurrencies(@Req() request: Request): Promise<UserCurrenciesResponseDto> {
    return this.currencies.userCurrencies(request.session.principal!.userId);
  }

  @Post('users/me/currencies')
  @ApiOperation({ summary: 'Add a supported currency within the current entitlement' })
  @ApiBody({
    type: AddUserCurrencyDto,
    examples: { currency: { value: { code: 'EUR' } } },
  })
  @ApiCreatedResponse({ type: UserCurrenciesResponseDto })
  add(
    @Body() dto: AddUserCurrencyDto,
    @Req() request: Request,
  ): Promise<UserCurrenciesResponseDto> {
    return this.currencies.add(request.session.principal!.userId, dto.code);
  }

  @Delete('users/me/currencies/:code')
  @HttpCode(204)
  @ApiOperation({ summary: 'Remove a non-main currency membership' })
  @ApiNoContentResponse()
  async remove(@Param('code') code: string, @Req() request: Request): Promise<void> {
    await this.currencies.remove(request.session.principal!.userId, code);
  }

  @Put('users/me/main-currency')
  @ApiOperation({ summary: 'Atomically select exactly one owned main currency' })
  @ApiBody({
    type: SetMainCurrencyDto,
    examples: { mainCurrency: { value: { code: 'EUR' } } },
  })
  @ApiOkResponse({ type: UserCurrenciesResponseDto })
  setMain(
    @Body() dto: SetMainCurrencyDto,
    @Req() request: Request,
  ): Promise<UserCurrenciesResponseDto> {
    return this.currencies.setMain(request.session.principal!.userId, dto.code);
  }
}
