import { makeStyles } from 'tss-react/mui';
import * as BaseTokens from '../../../../base/tokens/themes/base.tokens';

export type NodeSize = 'compact' | 'default';

const nodeSizes: Record<NodeSize, { width: number, height: number }> = {
  compact: {
    width: 60,
    height: 26
  },
  default: {
    width: 176,
    height: 26
  }
};

const paddingHorizontal = 12;

const useStyles = makeStyles()((theme) => ({
  resourceNode: {
    '&[data-align="center"]': {},
    '&[data-size="compact"]': {
      '--node-width': `${nodeSizes.compact.width}px`,
      '--node-height': `${nodeSizes.compact.height}px`
    },
    '&[data-size="default"]': {
      '--node-width': `${nodeSizes.default.width}px`,
      '--node-height': `${nodeSizes.default.height}px`
    },
    '.bg': {
      fill: BaseTokens.colorGrey800,
      rx: 6,
      width: 'var(--node-width)',
      height: 'var(--node-height)',
      x: 'calc(-1 * var(--node-width) / 2)',
      y: 'calc(-1 * var(--node-height) / 2)'
    },
    '.connectors-mask': {
      '.bg': {
        fill: 'white',
        width: 'var(--node-width)',
        height: 'var(--node-height)',
        x: 'calc(-1 * var(--node-width) / 2)',
        y: 'calc(-1 * var(--node-height) / 2)'
      },
      '& .connector': {
        fill: 'black',
        r: 2,
        cy: 0,
        '&:first-of-type': {
          cx: 'calc(-1 * var(--node-width) / 2)'
        },
        '&:last-of-type': {
          cx: 'calc(var(--node-width) / 2)'
        }
      }
    },
    '.label': {
      width: 'calc(var(--node-width) - 60px)',
      height: 'var(--node-height)',
      x: 'calc(-1 * var(--node-width) / 2)',
      y: 'calc(-1 * var(--node-height) / 2)',

      '& div': {
        height: 'inherit',
        display: 'flex',
        padding: `0 0 1px ${paddingHorizontal}px`,
        alignItems: 'center',
        '& span': {
          color: BaseTokens.colorWhite,
          fontSize: 12,
          fontWeight: 500,
          fontFamily: 'Roboto',
          letterSpacing: 0.15,
          lineHeight: '16px',
          textOverflow: 'ellipsis',
          overflow: 'hidden',
          textWrap: 'noWrap'
        }
      }
    },
    '.indicators': {
      width: '36px',
      translate: `calc(var(--node-width) / 2 - ${paddingHorizontal}px - 36px)`
    },
    '.group': {
      width: '14px',
      transform: 'scale(calc(16 / 24)) translateY(calc(-24px / 2))',
      '& svg': {
        fill: BaseTokens.colorWhite
      }
    },
    '.status': {
      width: '14px',
      r: 4,
      cx: `calc(36px - 14px / 2)`
    },
    '&[data-status="neutral"] .status, .status': {
      fill: '#71C4DE'
    },
    '&[data-status="ok"] .status': {
      fill: '#86DBB2'
    },
    '&[data-status="warn"] .status': {
      fill: '#D9B75F'
    },
    '&[data-status="error"] .status': {
      fill: '#E3796A'
    }
  }
}));

export { useStyles, nodeSizes };
