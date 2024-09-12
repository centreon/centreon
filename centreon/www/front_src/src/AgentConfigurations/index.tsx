import {
  FileDropZone,
  Method,
  transformFileListToArray,
  useMutationQuery
} from '@centreon/ui';
import { Button } from '@centreon/ui/components';
import { Box } from '@mui/material';
import { useState } from 'react';

const Page = (): JSX.Element => {
  const [files, setFiles] = useState<FileList | null>(null);

  const { mutateAsync } = useMutationQuery({
    getEndpoint: () => '/test',
    method: Method.POST,
    fetchHeaders: {
      'Content-Type': 'multipart/form-data'
    }
  });

  const send = (): void => {
    const formData = new FormData();

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
      ]
    };

    formData.append('data', JSON.stringify(payload));
    transformFileListToArray(files).forEach((file, index) => {
      formData.append(
        `file_${index}_${file.name}`,
        new Blob([file]),
        file.name
      );
    });

    mutateAsync({
      payload: formData
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
        files={files}
        changeFiles={setFiles}
        multiple={false}
        resetFilesStatusAndUploadData={() => setFiles(null)}
      />
      <Button onClick={send}>Send payload</Button>
    </Box>
  );
};

export default Page;
