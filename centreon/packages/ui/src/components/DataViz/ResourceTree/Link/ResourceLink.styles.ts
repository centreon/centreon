import { makeStyles } from 'tss-react/mui';
import * as BaseTokens from '../../../../base/tokens/themes/base.tokens';

const useStyles = makeStyles()((theme) => ({
  resourceLink: {
    fill: 'none',
    stroke: BaseTokens.colorGrey300,
    strokeWidth: '1'
  }
}));

export { useStyles };
