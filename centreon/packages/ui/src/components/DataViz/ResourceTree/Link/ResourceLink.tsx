import React, { ReactElement } from 'react';
import { HierarchyPointLink, HierarchyPointNode } from '@visx/hierarchy/lib/types';

import { TreeNode } from '../ResourceTree.types';
import { useStyles } from './ResourceLink.styles';
import { LinkHorizontal } from '@visx/shape';
import { nodeSizes } from '../Node/ResourceNode.styles';

type ResourceLinkProps = {
  link: HierarchyPointLink<TreeNode>;

};

const ResourceLink = ({link}: ResourceLinkProps): ReactElement => {
  const {classes} = useStyles();

  const offset = nodeSizes.default.width / 2;
  const isSource = (d: HierarchyPointNode<TreeNode>) => d.depth === link.source.depth;
  const isTarget = (d: HierarchyPointNode<TreeNode>) => d.depth === link.target.depth;


  return (
    <LinkHorizontal
      className={classes.resourceLink}
      data={link}
      x={(d: HierarchyPointNode<TreeNode>) =>
        d.y + (isSource(d) ? offset : isTarget(d) ? -offset : 0)
      }
    />
  );
};

export { ResourceLink };
