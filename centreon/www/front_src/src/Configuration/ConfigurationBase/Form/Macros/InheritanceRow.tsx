import type { ReactElement, ReactNode } from 'react';

export type InheritanceTone = 'success' | 'warning';

// Written out in full so Tailwind finds them.
const toneClassName: Record<InheritanceTone, string> = {
  success: 'bg-success-light',
  warning: 'bg-warning-light'
};

interface InheritanceSwatchProps {
  tone: InheritanceTone;
}

// The legend's marker of a tone, the same colour as the bar of its rows.
export const InheritanceSwatch = ({
  tone
}: InheritanceSwatchProps): ReactElement => (
  <span
    aria-hidden
    className={`inline-block size-3 rounded-xs ${toneClassName[tone]}`}
  />
);

interface InheritanceRowProps {
  children: ReactNode;
  barTestId?: string;
  // What the row is inherited from. Without one, the bar is left out but its
  // room is kept, so inherited and own rows line up.
  tone?: InheritanceTone;
}

// A row with the design's 4px by 36px bar on its left when it is inherited.
const InheritanceRow = ({
  barTestId,
  children,
  tone
}: InheritanceRowProps): ReactElement => (
  <div className="flex gap-2">
    <span
      aria-hidden
      className={`h-9 w-1 shrink-0 self-center rounded-xs ${
        tone ? toneClassName[tone] : ''
      }`}
      data-testid={barTestId}
    />
    <div className="min-w-0 flex-1">{children}</div>
  </div>
);

export default InheritanceRow;
