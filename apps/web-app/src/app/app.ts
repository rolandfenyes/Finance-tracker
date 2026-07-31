import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { TranslocoPipe } from '@jsverse/transloco';

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [RouterOutlet, TranslocoPipe],
  selector: 'mmm-root',
  templateUrl: './app.html',
  styleUrl: './app.scss',
})
export class App {}
