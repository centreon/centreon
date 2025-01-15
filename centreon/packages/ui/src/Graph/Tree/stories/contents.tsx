import { T, always, cond, equals, has } from 'ramda';

import SpaIcon from '@mui/icons-material/Spa';
import { Avatar, Paper, Typography, useTheme } from '@mui/material';

import { ChildrenProps } from '..';

import { ComplexData, SimpleData } from './datas';

export const SimpleContent = ({
  node,
  depth,
  isExpanded,
  nodeSize,
  expandCollapseNode
}: ChildrenProps<SimpleData>): JSX.Element => {
  const theme = useTheme();
  const fillColor = cond([
    [equals('critical'), always(theme.palette.error.main)],
    [equals('warning'), always(theme.palette.warning.main)],
    [T, always(theme.palette.success.main)]
  ])(node.data.status);

  if (equals(depth, 0)) {
    return (
      <div
        style={{
          alignItems: 'center',
          display: 'flex',
          height: '100%',
          justifyContent: 'center',
          width: '100%'
        }}
      >
        <Avatar
          sx={{
            backgroundColor: fillColor,
            color: theme.palette.text.primary,
            cursor: 'pointer'
          }}
        >
          {node.data.name}
        </Avatar>
      </div>
    );
  }

  return (
    <Paper
      sx={{
        alignItems: 'center',
        backgroundColor: fillColor,
        cursor: node.children ? 'pointer' : 'default',
        display: 'flex',
        flexDirection: 'column',
        height: nodeSize.height,
        justifyContent: 'center',
        p: 1,
        position: 'relative',
        width: nodeSize.width
      }}
      onClick={() => {
        expandCollapseNode(node);
      }}
    >
      {!node.children && (
        <SpaIcon
          fontSize="small"
          sx={{ position: 'absolute', right: 8, top: 8 }}
        />
      )}
      <Typography>{node.data.name}</Typography>
      {node.children && (
        <Typography>{isExpanded ? 'Expanded' : 'Collapsed'}</Typography>
      )}
    </Paper>
  );
};

export const ComplexContent = ({
  node,
  depth,
  nodeSize,
  expandCollapseNode,
  onMouseDown,
  onMouseUp
}: ChildrenProps<ComplexData>): JSX.Element => {
  const theme = useTheme();
  const fillColor = cond([
    [equals('critical'), always(theme.palette.error.main)],
    [equals('warning'), always(theme.palette.warning.main)],
    [T, always(theme.palette.success.main)]
  ])(node.data.status);

  if (equals(depth, 0)) {
    return (
      <Paper
        sx={{
          alignItems: 'center',
          backgroundColor: fillColor,
          display: 'flex',
          flexDirection: 'column',
          height: nodeSize.height,
          justifyContent: 'center',
          p: 1,
          position: 'relative',
          width: nodeSize.width
        }}
      >
        <Typography>{node.data.name}</Typography>
      </Paper>
    );
  }

  if (has('count', node.data)) {
    return (
      <div
        style={{
          alignItems: 'center',
          display: 'flex',
          height: '100%',
          justifyContent: 'center',
          width: '100%'
        }}
      >
        <Avatar
          sx={{
            backgroundColor: fillColor,
            color: theme.palette.text.primary,
            cursor: 'pointer'
          }}
          onMouseDown={onMouseDown}
          onMouseUp={onMouseUp(() => expandCollapseNode(node))}
        >
          {node.data.count}
        </Avatar>
      </div>
    );
  }

  return (
    <Paper
      sx={{
        alignItems: 'center',
        backgroundColor: fillColor,
        display: 'flex',
        flexDirection: 'column',
        height: nodeSize.height,
        justifyContent: 'center',
        p: 1,
        position: 'relative',
        width: nodeSize.width
      }}
    >
      {!node.children && (
        <SpaIcon
          fontSize="small"
          sx={{ position: 'absolute', right: 8, top: 8 }}
        />
      )}
      <Typography>{node.data.name}</Typography>
    </Paper>
  );
};
