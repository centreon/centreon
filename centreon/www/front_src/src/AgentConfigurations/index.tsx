import {
  FileDropZone,
  Method,
  transformFileListToArray,
  useMutationQuery
} from '@centreon/ui';
import { Button } from '@centreon/ui/components';
import { Box } from '@mui/material';
import { useState } from 'react';

const toDataUrl = (file: File | Blob) =>
  new Promise((resolve, reject) => {
    const fileReader = new FileReader();
    fileReader.onloadend = () => resolve(fileReader.result);
    fileReader.onerror = () => reject(fileReader.error);
    fileReader.readAsDataURL(file);
  });

const Page = (): JSX.Element => {
  const [files, setFiles] = useState<FileList | null>(null);

  const { mutateAsync } = useMutationQuery({
    getEndpoint: () => '/test',
    method: Method.POST
  });

  const changeFiles = (newFiles: FileList): void => {
    Promise.all(transformFileListToArray(newFiles).map(toDataUrl)).then(
      setFiles
    );
  };

  const send = (): void => {
    const payload = {
      name: 'heyyy',
      type: 'telegraf',
      parameters: [
        {
          poller: 'Pauler'
        },
        {
          poller: 'Paulaner'
        }
      ],
      files
    };

    mutateAsync({
      payload: payload
    });
  };

  return (
    <Box
      sx={{
        display: 'flex',
        flexDirection: 'row',
        gap: 3,
        alignItems: 'center',
        height: 'min-content'
      }}
    >
      <FileDropZone
        accept="*"
        files={null}
        changeFiles={changeFiles}
        multiple={false}
        resetFilesStatusAndUploadData={() => setFiles(null)}
      />
      <Button onClick={send}>Send payload</Button>
    </Box>
  );
};

export default Page;
