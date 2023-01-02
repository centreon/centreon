import { useState } from 'react';

import { makeStyles } from 'tss-react/mui';

import { Button, Paper, Typography, Theme } from '@mui/material';
import PersonIcon from '@mui/icons-material/Person';

import FileDropZone, {
  CustomDropZoneContentProps,
  transformFileListToArray
} from '.';

const useStyles = makeStyles()((theme: Theme) => ({
  root: {
    background: theme.palette.divider,
    borderColor: theme.palette.primary.dark,
    color: theme.palette.primary.dark
  }
}));

export default {
  title: 'File Drop Zone'
};

interface Props {
  CustomDropZoneContent?: (props: CustomDropZoneContentProps) => JSX.Element;
  accept: string;
  className?: string;
  maxFileSize?: number;
  multiple: boolean;
}

const Story = ({
  accept,
  multiple,
  CustomDropZoneContent,
  maxFileSize,
  className
}: Props): JSX.Element => {
  const [files, setFiles] = useState<FileList | null>(null);

  return (
    <Paper elevation={0}>
      <FileDropZone
        CustomDropZoneContent={CustomDropZoneContent}
        accept={accept}
        changeFiles={setFiles}
        className={className}
        files={files}
        maxFileSize={maxFileSize}
        multiple={multiple}
        resetFilesStatusAndUploadData={(): void => setFiles(null)}
      />
      {files &&
        transformFileListToArray(files).map((file) => (
          <Typography key={file.name}>{file.name}</Typography>
        ))}
    </Paper>
  );
};

export const basicSingleImage = (): JSX.Element => (
  <Story accept="image/*" multiple={false} />
);

export const basicMultipleImage = (): JSX.Element => (
  <Story multiple accept="image/*" />
);

export const basicSingleCustomExtension = (): JSX.Element => (
  <Story accept="image/png,.pdf,.license" multiple={false} />
);

const DropZoneContent = ({
  openFileExplorer
}: CustomDropZoneContentProps): JSX.Element => (
  <div style={{ height: '200px', position: 'relative', width: '200px' }}>
    <PersonIcon
      style={{ height: '100%', position: 'absolute', width: '100%' }}
    />
    <Button
      style={{ bottom: '0', position: 'absolute', right: '0' }}
      onClick={openFileExplorer}
    >
      Open file explorer
    </Button>
  </div>
);

export const basicSingleImageWithACustomDropZoneContent = (): JSX.Element => (
  <Story
    CustomDropZoneContent={DropZoneContent}
    accept="image/*"
    multiple={false}
  />
);

export const basicSingleImageWithMaxFileSize = (): JSX.Element => (
  <Story accept="image/*" maxFileSize={1_000_000} multiple={false} />
);

const CustomFileDropZone = (): JSX.Element => {
  const { classes } = useStyles();

  return <Story accept="image/*" className={classes.root} multiple={false} />;
};

export const customFileDropZone = (): JSX.Element => <CustomFileDropZone />;
