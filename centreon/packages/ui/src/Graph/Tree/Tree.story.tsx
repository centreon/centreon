import { equals } from 'ramda';

import { StandaloneTree, type TreeProps } from '.';
import { ComplexContent, SimpleContent } from './stories/contents';
import {
  type ComplexData,
  complexData,
  type SimpleData,
  simpleData
} from './stories/datas';

interface Props {
  complex?: boolean;
  /** Expands only the critical nodes by default. */
  expandCritical?: boolean;
  /** Draws the links after the status of their target. */
  styledLinks?: boolean;
  linkType?: 'curve' | 'line' | 'step';
}

const styledLinks: TreeProps<SimpleData>['treeLink'] = {
  getStroke: ({ target }) => (target.status === 'ok' ? 'grey' : 'black'),
  getStrokeDasharray: ({ target }) => (target.status === 'ok' ? '5,5' : '0'),
  getStrokeOpacity: ({ target }) => (target.status === 'ok' ? 0.8 : 1),
  getStrokeWidth: ({ target }) => (target.status === 'ok' ? 1 : 2)
};

export const Default = ({
  complex = false,
  expandCritical = false,
  linkType,
  styledLinks: styled = false
}: Props): JSX.Element => (
  <div style={{ height: '99vh' }}>
    <StandaloneTree<SimpleData | ComplexData>
      node={{
        height: 70,
        isDefaultExpanded: expandCritical
          ? (data) => equals('critical', (data as SimpleData).status)
          : undefined,
        width: 70
      }}
      tree={(complex ? complexData : simpleData) as never}
      treeLink={
        (styled
          ? styledLinks
          : linkType
            ? { type: linkType }
            : undefined) as never
      }
    >
      {(complex ? ComplexContent : SimpleContent) as never}
    </StandaloneTree>
  </div>
);
