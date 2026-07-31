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
  ApiBody,
  ApiConflictResponse,
  ApiCookieAuth,
  ApiCreatedResponse,
  ApiNoContentResponse,
  ApiNotFoundResponse,
  ApiOkResponse,
  ApiOperation,
  ApiTags,
  ApiUnprocessableEntityResponse,
} from '@nestjs/swagger';
import type { Request } from 'express';
import { AuthenticationGuard, VerifiedEmailGuard } from '../identity/authentication.guard';
import { PersonalFinanceAccessGuard } from '../users/entitlements.service';
import {
  AssignBudgetRuleDto,
  BasicIncomesResponseDto,
  BudgetRulesQueryDto,
  BudgetRulesResponseDto,
  CategoriesResponseDto,
  CreateBasicIncomeDto,
  CreateBudgetRuleDto,
  CreateCategoryDto,
  ReplaceBudgetRulesDto,
  UpdateBasicIncomeDto,
  UpdateBudgetRuleDto,
  UpdateCategoryDto,
} from './budgeting.dto';
import { BudgetingService } from './budgeting.service';

@ApiCookieAuth()
@UseGuards(AuthenticationGuard, VerifiedEmailGuard, PersonalFinanceAccessGuard)
@Controller()
export class BudgetingController {
  constructor(@Inject(BudgetingService) private readonly budgeting: BudgetingService) {}

  @Get('budget-rules')
  @ApiTags('Budgeting')
  @ApiOperation({
    summary: 'List exact percentage rules, assignments, allocation status, and optional month plan',
  })
  @ApiOkResponse({ type: BudgetRulesResponseDto })
  rules(
    @Req() request: Request,
    @Query() query: BudgetRulesQueryDto,
  ): Promise<BudgetRulesResponseDto> {
    return this.budgeting.rules(request.session.principal!.userId, query.month);
  }

  @Put('budget-rules')
  @ApiTags('Budgeting')
  @ApiOperation({ summary: 'Configure the initial onboarding budget-rule set atomically' })
  @ApiBody({
    type: ReplaceBudgetRulesDto,
    examples: {
      initialRules: {
        value: {
          rules: [
            { label: 'Needs', percent: '50' },
            { label: 'Goals', percent: '30', targetHint: 'Descriptive target only' },
          ],
        },
      },
    },
  })
  @ApiOkResponse({ type: BudgetRulesResponseDto })
  @ApiConflictResponse({ description: 'Initial rules were already configured' })
  initialize(
    @Body() dto: ReplaceBudgetRulesDto,
    @Req() request: Request,
  ): Promise<BudgetRulesResponseDto> {
    const principal = request.session.principal!;
    return this.budgeting.initializeRules(principal.userId, principal.role, dto.rules);
  }

  @Post('budget-rules')
  @ApiTags('Budgeting')
  @ApiOperation({ summary: 'Create a premium cash-flow rule' })
  @ApiBody({
    type: CreateBudgetRuleDto,
    examples: { rule: { value: { label: 'Needs', percent: '50' } } },
  })
  @ApiCreatedResponse({ type: BudgetRulesResponseDto })
  createRule(
    @Body() dto: CreateBudgetRuleDto,
    @Req() request: Request,
  ): Promise<BudgetRulesResponseDto> {
    const principal = request.session.principal!;
    return this.budgeting.createRule(principal.userId, principal.role, dto);
  }

  @Patch('budget-rules/:id')
  @ApiTags('Budgeting')
  @ApiOperation({ summary: 'Update an owned premium cash-flow rule' })
  @ApiOkResponse({ type: BudgetRulesResponseDto })
  @ApiNotFoundResponse({ description: 'The owned rule was not found' })
  updateRule(
    @Param('id', new ParseUUIDPipe({ version: '4' })) ruleId: string,
    @Body() dto: UpdateBudgetRuleDto,
    @Req() request: Request,
  ): Promise<BudgetRulesResponseDto> {
    const principal = request.session.principal!;
    return this.budgeting.updateRule(principal.userId, principal.role, ruleId, dto);
  }

  @Delete('budget-rules/:id')
  @HttpCode(204)
  @ApiTags('Budgeting')
  @ApiOperation({ summary: 'Delete an owned rule and leave assigned categories unassigned' })
  @ApiNoContentResponse()
  async deleteRule(
    @Param('id', new ParseUUIDPipe({ version: '4' })) ruleId: string,
    @Req() request: Request,
  ): Promise<void> {
    const principal = request.session.principal!;
    await this.budgeting.deleteRule(principal.userId, principal.role, ruleId);
  }

  @Get('categories')
  @ApiTags('Categories')
  @ApiOperation({ summary: 'List owned income and spending categories' })
  @ApiOkResponse({ type: CategoriesResponseDto })
  categories(@Req() request: Request): Promise<CategoriesResponseDto> {
    return this.budgeting.categories(request.session.principal!.userId);
  }

  @Post('categories')
  @ApiTags('Categories')
  @ApiOperation({ summary: 'Create an owned category within the current plan quota' })
  @ApiBody({
    type: CreateCategoryDto,
    examples: {
      spending: {
        value: { label: 'Groceries', kind: 'spending', color: '#FACC15' },
      },
    },
  })
  @ApiCreatedResponse({ type: CategoriesResponseDto })
  createCategory(
    @Body() dto: CreateCategoryDto,
    @Req() request: Request,
  ): Promise<CategoriesResponseDto> {
    return this.budgeting.createCategory(request.session.principal!.userId, dto);
  }

  @Patch('categories/:id')
  @ApiTags('Categories')
  @ApiOperation({ summary: 'Update an unprotected owned category' })
  @ApiOkResponse({ type: CategoriesResponseDto })
  updateCategory(
    @Param('id', new ParseUUIDPipe({ version: '4' })) categoryId: string,
    @Body() dto: UpdateCategoryDto,
    @Req() request: Request,
  ): Promise<CategoriesResponseDto> {
    return this.budgeting.updateCategory(request.session.principal!.userId, categoryId, dto);
  }

  @Put('categories/:id/budget-rule')
  @ApiTags('Categories')
  @ApiOperation({ summary: 'Assign or clear a premium rule on an owned spending category' })
  @ApiBody({
    type: AssignBudgetRuleDto,
    examples: {
      assign: { value: { budgetRuleId: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa' } },
      clear: { value: { budgetRuleId: null } },
    },
  })
  @ApiOkResponse({ type: CategoriesResponseDto })
  assignRule(
    @Param('id', new ParseUUIDPipe({ version: '4' })) categoryId: string,
    @Body() dto: AssignBudgetRuleDto,
    @Req() request: Request,
  ): Promise<CategoriesResponseDto> {
    const principal = request.session.principal!;
    return this.budgeting.assignCategoryRule(
      principal.userId,
      principal.role,
      categoryId,
      dto.budgetRuleId,
    );
  }

  @Delete('categories/:id')
  @HttpCode(204)
  @ApiTags('Categories')
  @ApiOperation({ summary: 'Delete an unprotected, unreferenced owned category' })
  @ApiNoContentResponse()
  @ApiConflictResponse({ description: 'The category is protected or referenced by financial data' })
  async deleteCategory(
    @Param('id', new ParseUUIDPipe({ version: '4' })) categoryId: string,
    @Req() request: Request,
  ): Promise<void> {
    await this.budgeting.deleteCategory(request.session.principal!.userId, categoryId);
  }

  @Get('basic-incomes')
  @ApiTags('Basic income')
  @ApiOperation({ summary: 'List recurring baseline income planning definitions' })
  @ApiOkResponse({ type: BasicIncomesResponseDto })
  basicIncomes(@Req() request: Request): Promise<BasicIncomesResponseDto> {
    return this.budgeting.basicIncomes(request.session.principal!.userId);
  }

  @Post('basic-incomes')
  @ApiTags('Basic income')
  @ApiOperation({ summary: 'Create a forecast-only baseline income definition' })
  @ApiBody({
    type: CreateBasicIncomeDto,
    examples: {
      salary: {
        value: {
          label: 'Salary',
          amount: '1000.00',
          currency: 'HUF',
          validFrom: '2026-07-01',
        },
      },
    },
  })
  @ApiCreatedResponse({ type: BasicIncomesResponseDto })
  @ApiUnprocessableEntityResponse({
    description: 'Amount, currency, income category, or date-range invariant failed',
  })
  createBasicIncome(
    @Body() dto: CreateBasicIncomeDto,
    @Req() request: Request,
  ): Promise<BasicIncomesResponseDto> {
    return this.budgeting.createBasicIncome(request.session.principal!.userId, dto);
  }

  @Patch('basic-incomes/:id')
  @ApiTags('Basic income')
  @ApiOperation({ summary: 'Update an owned baseline income definition without posting income' })
  @ApiOkResponse({ type: BasicIncomesResponseDto })
  updateBasicIncome(
    @Param('id', new ParseUUIDPipe({ version: '4' })) incomeId: string,
    @Body() dto: UpdateBasicIncomeDto,
    @Req() request: Request,
  ): Promise<BasicIncomesResponseDto> {
    return this.budgeting.updateBasicIncome(request.session.principal!.userId, incomeId, dto);
  }

  @Delete('basic-incomes/:id')
  @HttpCode(204)
  @ApiTags('Basic income')
  @ApiOperation({ summary: 'Delete an owned baseline income planning definition' })
  @ApiNoContentResponse()
  async deleteBasicIncome(
    @Param('id', new ParseUUIDPipe({ version: '4' })) incomeId: string,
    @Req() request: Request,
  ): Promise<void> {
    await this.budgeting.deleteBasicIncome(request.session.principal!.userId, incomeId);
  }
}
