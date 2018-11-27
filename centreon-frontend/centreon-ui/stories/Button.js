import React from 'react';
import { storiesOf } from '@storybook/react';
import { Button } from '../src';


storiesOf('Button', module)
    .add('with text', () => (<Button
        label={'Hey'}
        onClick={() => { }}
    />
    ), {
            notes: 'A very simple component',
        });

storiesOf('Button', module)
    .add('with colered text', () => <Button
        label={'Hey'}
        color={'red'}
        onClick={() => { alert('Hey') }}
    />);