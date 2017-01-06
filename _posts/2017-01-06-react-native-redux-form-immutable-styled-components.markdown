---
layout:     post
title:      "Simple React Native forms with redux-form, immutable.js and styled-components"
subtitle:   "How to easily integrate user input into your state management on the mobile platform"
date:       2017-01-06 05:01:00 +0200
author:     "Esben Petersen"
header-img: "img/post-bg-04.jpg"
---

## tl;dr

<p>
    If you just want to see how you can use redux-form and immutable.js in react native, you can find
    <a target="_blank" href="https://github.com/esbenp/react-native-redux-form-example">
    the code for this article can be found right here</a>.
</p>

## Introduction

<p>
  At <a href="https://traede.com" target="_blank">Traede</a> we use
  <a target="_blank" href="https://github.com/erikras/redux-form">redux-form</a> for creating forms
  that integrate well with <a target="_blank" href="https://github.com/reactjs/redux">redux</a>. It is
  a fine library, however there are not many examples on how to make it work with
  <a target="_blank" href="https://facebook.github.io/react-native/">React Native</a>. So when I started
  playing around with React Native naturally I used redux-form for my form management and found it
  to be a bit difficult. Mostly because <a href="https://github.com/erikras/redux-form/pull/2336">I found a bug</a>
  that broke redux-form on native platform when using
  <a target="_blank" href="https://github.com/facebook/immutable-js">immutable.js</a>. My PR has been released
  as of version 6.4.2. So I thought I would write a bit of documentation on how to use redux-form and
  immutable.js for form management based on my learnings along the way.
</p>

## Agenda

<p>
  The steps we will go through is...
</p>

<ol>
  <li>See how redux-form differs on native vs. web in the most simple way</li>
  <li>See how we can make it work using immutable.js</li>
  <li>A more complete example using react-native-clean-form elements</li>
</ol>

<p>
  Excited? Lets go..!
</p>

## Using redux-form with React Native

<p>
    If you are unfamiliar with redux-form I suggest you
    <a target="_blank" href="http://redux-form.com/6.4.3/docs/GettingStarted.md/">go read the Get Started documentation</a>.
    <strong>Note this guide assumes that you are using redux-form version <code>>=6.4.2</code></strong>. Otherwise, if you
    are using Immutable.js you are going to have issues with
    <a target="_blank" href="https://github.com/erikras/redux-form/pull/2336">redux-form#2336</a>.
    To create a form there are basically three steps:
</p>

<ol>
    <li>Add the redux-form reducer to your redux store</li>
    <li>Connect your form to the store using the <code>reduxForm</code> wrapper</li>
    <li>Connect specific fields to the store using the <code>Field</code> wrapper</li>
</ol>

### 0. Create a React Native project

<p>
    I am assuming you already have a React Native project ready to go. If not you can easily create one
    using <code>react-native init MyReduxFormProject</code>.
</p>

### 1. Add the redux-form reducer to your redux store

<p>
    For this step, please
    <a target="_blank" href="http://redux-form.com/6.4.3/docs/GettingStarted.md/">consult the redux-form documentation</a>.
</p>

### 2. Connect your form to the store using the redux-form wrapper

<img align="right" style="margin-left:20px" src="/img/react-native-redux-form/simple-form.jpg">

<p>
    Okay, so let us start out with the simplest of forms and then connect
    that to redux-form. So, the code below will generate the screen on the right.
</p>

```
import React from 'react'
import {
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View
} from 'react-native'

const Form = props => {
  return (
    <View style={styles.container}>
      <Text>Email:</Text>
      <TextInput style={styles.input} />
      <TouchableOpacity>
        <Text style={styles.button}>Submit</Text>
      </TouchableOpacity>
    </View>
  )
}

export default Form

const styles = StyleSheet.create({
  button: {
    backgroundColor: 'blue',
    color: 'white',
    height: 30,
    lineHeight: 30,
    marginTop: 10,
    textAlign: 'center',
    width: 250
  },
  container: {

  },
  input: {
    borderColor: 'black',
    borderWidth: 1,
    height: 37,
    width: 250
  }
})
```

<p>
  Alright, so we have our form and it already looks like a billion dollar app (unicorn alert).
  Next, we need to connect the form to the redux store using the <code>reduxForm</code> wrapper. This is
  because every key press in the form will send the value of the input field to store in the
  form. When we press the submit button redux-form will extract all the saved values from
  the store to a callback function we specify.
</p>

```
import { reduxForm } from 'redux-form'

const submit = values => {
  console.log('submitting form', values)
}

const Form = props => {
  const { handleSubmit } = props

  return (
    <View style={styles.container}>
      <Text>Email:</Text>
      <TextInput style={styles.input} />
      <TouchableOpacity onPress={handleSubmit(submit)}>
        <Text style={styles.button}>Submit</Text>
      </TouchableOpacity>
    </View>
  )
}

export default reduxForm({
  form: 'test'
})(Form)
```

<p class="note">
  NOTE: I left out the stylesheet declaration and the react-native imports for brevity.
</p>

<p>
  Okay, so first of all we wrapped the form to connect it to the store using <code>reduxForm</code>.
  This is basically a modified version of react-redux's <code>connect</code> you are probably
  familiar with.
</p>

<p>
  Next, we create our submit function using redux-form's <code>handleSubmit</code> (which
  <code>reduxForm</code> injects into our component). The submit function is attached
  to our submit button so when it is pressed the form is submitted. This is different from
  web development where the submit function is attached to a <code>form</code> element. On
  mobile platforms there is no form element so we attach it directly to the button. Or
  <code>TouchableOpacity</code> that is...
</p>

<p>
  At this point try and run the code using a simulator. I also highly recommend using
  <a target="_blank" href="https://github.com/jhen0409/react-native-debugger">react-native-debugger</a>
  as a debugger. You can also check out the
  <a target="_blank" href="https://facebook.github.io/react-native/docs/debugging.html">React Native
  documentation on debugging</a> for suggestions.
</p>

<p>
  Either way, when you try and submit the form in the simulator you will see that our callback function
  provides empty values.
</p>

<img src="/img/react-native-redux-form/submit-console-1.jpg" />

<p class="note">
  Yo, where my values?
</p>

### 3. Connect the form fields to the store using the Field wrapper

<p>
  So the way redux-form works is that you have to connect each field to the store using another
  wrapper named <code>Field</code>.
</p>

```
import { Field, reduxForm } from 'redux-form'

const submit = values => {
  console.log('submitting form', values)
}

const renderInput = ({ input: { onChange, ...restInput }}) => {
  return <TextInput style={styles.input} onChangeText={onChange} {...restInput} />
}

const Form = props => {
  const { handleSubmit } = props

  return (
    <View style={styles.container}>
      <Text>Email:</Text>
      <Field name="email" component={renderInput} />
      <TouchableOpacity onPress={handleSubmit(submit)}>
        <Text style={styles.button}>Submit</Text>
      </TouchableOpacity>
    </View>
  )
}

export default reduxForm({
  form: 'test'
})(Form)
```

<p>
  Note we add the <code>Field</code> component and give it a <code>name</code> prop, much
  similar to how the <code>input</code> field works in web development. We also add a
  render function that tells reduxForm how the field should be rendered (which is
  basically just a <code>TextInput</code>).
</p>

<p>
  Now, this is where it gets tricky and what most people get wrong. <strong>So pay close attention</strong>.
  In web React an <code>input</code> component triggers an <code>onChange</code> callback when the value
  of the field changes. In React Native the <code>TextInput</code> callback is named <code>onChangeText</code>.
  To account for this we add the change handler manually <code>onChangeText={onChange}</code>.
</p>

<img src="/img/react-native-redux-form/submit-console-2.jpg">

<p>
  Now when we submit our form it works! Awesomesauce.
</p>

## Making it work using Immutable.js

<p>
  If you are trendy and using Immutable.js for your state management then you need to take some extra steps
  to make redux-form work. I suggest you read
  <a target="_blank" href="http://redux-form.com/6.4.3/examples/immutable/">the official documentation on using Immutable.js with redux-form</a>.
  But we will also go through the steps right here.
</p>

### 1. Use redux-immutablejs combineReducers and the immutable version of redux-forms reducer

<p>
  Quite the mouthful. Alright, find the place where you create your redux store.
</p>


```
import { combineReducers } from 'redux-immutablejs'
import { reducer as form } from 'redux-form/immutable' // <--- immutable import

const reducer = combineReducers({ form })

export default reducer
```

<p>
  Two things here: (1) you must use <code>combineReducers</code> from a redux-immutable
  integration library such as <a target="_blank" href="https://github.com/indexiatech/redux-immutablejs">redux-immutablejs</a>
  or <a target="_blank" href="https://github.com/gajus/redux-immutable">redux-immutable</a>.
  The important thing here is that you <strong>import the reducer from <code>redux-form/immutable</code> and
  <u>NOT</u> <code>redux-form</code></strong>.
</p>

### 2. Use immutable version of reduxForm wrapper and Field

<p>
  Okay, so this step is similar to the first one. When you wrap a form in <code>reduxForm</code> to connect
  it to the redux store make <strong>sure you import from <code>redux-form/immutable</code></strong>!
  Similarly, <code>Field</code> must also be imported from there!
</p>

```
import { Field, reduxForm } from 'redux-form/immutable' // <---- LOOK HERE

const submit = values => {
  console.log('submitting form', values.toJS()) <--- use toJS() to cast to plain object
}

const renderInput = ({ input: { onChange, ...restInput }}) => {
  return <TextInput style={styles.input} onChangeText={onChange} {...restInput} />
}

const Form = props => {
  const { handleSubmit } = props

  return (
    <View style={styles.container}>
      <Text>Email:</Text>
      <Field name="email" component={renderInput} />
      <TouchableOpacity onPress={handleSubmit(submit)}>
        <Text style={styles.button}>Submit</Text>
      </TouchableOpacity>
    </View>
  )
}

export default reduxForm({
  form: 'test'
})(Form)
```

### 3. Done!

<p>
  That is it! Easy no? If you use a redux-version between <code>6.0.3</code> and <code>6.4.2</code>
  it will <strong>NOT WORK</strong> due to a regression introduced in <code>6.0.3</code>.
</p>

## Make it look like a billion dollar form using styled-components

<p>
  Using the absolutely awesome React styling library
  <a target="_blank" href="https://github.com/styled-components/styled-components">styled-components</a> I have
  created some redux-form integrated form elements that look a little bit better than our current iteration.
  <a target="_blank" href="https://dribbble.com/shots/3151351-Checkout-form">
    The look is strongly inspired by this Dribble shot by Artyom Khamitov</a> so all credit goes to him!
  You can find the source code for the elements here:
  <a target="_blank" href="https://github.com/esbenp/react-native-clean-form">esbenp/react-native-clean-form</a>.
</p>

### 1st step: Install react-native-clean-form

<p>
  Install the form elements using <code>npm install --save react-native-clean-form</code>. You also need to link
  the vector icon fonts to your app. <a target="_blank" href="https://github.com/esbenp/react-native-clean-form#installation">
  Read more about how in the README</a>.
</p>

### 2nd step: Design an awesome form

<img src="/img/react-native-redux-form/react-native-clean-form.jpg" />

<p>
  Looks dope, does it not? Lets dive right into the code.
</p>

```
import React, { Component } from 'react'
import {
  ActionsContainer,
  Button,
  FieldsContainer,
  Fieldset,
  Form,
  FormGroup,
  Label,
  Input,
  Select,
  Switch
} from 'react-native-clean-form'

const countryOptions = [
  {label: 'Denmark', value: 'DK'},
  {label: 'Germany', value: 'DE'},
  {label: 'United State', value: 'US'}
]

const FormView = props => (
  <Form>
    <FieldsContainer>
      <Fieldset label="Contact details">
        <FormGroup>
          <Label>First name</Label>
          <Input placeholder="John" />
        </FormGroup>
        <FormGroup>
          <Label>Last name</Label>
          <Input placeholder="Doe" />
        </FormGroup>
        <FormGroup>
          <Label>Phone</Label>
          <Input placeholder="+45 88 88 88 88" />
        </FormGroup>
        <FormGroup>
          <Label>First name</Label>
          <Input placeholder="John" />
        </FormGroup>
      </Fieldset>
      <Fieldset label="Shipping details" last>
        <FormGroup>
          <Label>Address</Label>
          <Input placeholder="Hejrevej 33" />
        </FormGroup>
        <FormGroup>
          <Label>City</Label>
          <Input placeholder="Copenhagen" />
        </FormGroup>
        <FormGroup>
          <Label>ZIP Code</Label>
          <Input placeholder="2400" />
        </FormGroup>
        <FormGroup>
          <Label>Country</Label>
          <Select
              name="country"
              label="Country"
              options={countryOptions}
              placeholder="Denmark"
          />
        </FormGroup>
        <FormGroup border={false}>
          <Label>Save my details</Label>
          <Switch />
        </FormGroup>
      </Fieldset>
    </FieldsContainer>
    <ActionsContainer>
      <Button icon="md-checkmark" iconPlacement="right">Save</Button>
    </ActionsContainer>
  </Form>
)

export default FormView
```

<p>
  If you are familiar with Twitter Bootstrap you can probably recognize some of the elements as react-native-clean-form
  strive to have a similar syntax. Alright, to connect it to redux-form we just have to import <code>Input</code>,
  <code>Select</code> and <code>Switch</code> from <code>react-native-clean-form/redux-form</code> or
  <code>react-native-clean-form/redux-form-immutable</code>. Here the elements are already wrapped in <code>FormGroup</code>
  and <code>Label</code> is added. Thereby we support validation feedback seen in the middle screenshot.
</p>

```
import React, { Component } from 'react'
import { reduxForm } from 'redux-form/immutable'
import {
  ActionsContainer,
  Button,
  FieldsContainer,
  Fieldset,
  Form
} from 'react-native-clean-form'
import {
  Input,
  Select,
  Switch
} from 'react-native-clean-form/redux-form-immutable'
import { View,Text } from 'react-native'

const onSubmit = (values, dispatch) => {
  return new Promise((resolve) => {
    setTimeout(() => {
      console.log(values.toJS())
      resolve()
    }, 1500)
  })
}

const countryOptions = [
  {label: 'Denmark', value: 'DK'},
  {label: 'Germany', value: 'DE'},
  {label: 'United State', value: 'US'}
]

class FormView extends Component {
  render() {
    const { handleSubmit, submitting } = this.props

    return (
      <Form>
        <FieldsContainer>
          <Fieldset label="Contact details">
            <Input name="first_name" label="First name" placeholder="John" />
            <Input name="last_name" label="Last name" placeholder="Doe" />
            <Input name="email" label="Email" placeholder="something@domain.com" />
            <Input name="telephone" label="Phone" placeholder="+45 88 88 88 88" />
          </Fieldset>
          <Fieldset label="Shipping details" last>
            <Input name="address" label="Address" placeholder="Hejrevej 33" />
            <Input name="city" label="City" placeholder="Copenhagen" />
            <Input name="zip" label="ZIP Code" placeholder="2400" />
            <Select
              name="country"
              label="Country"
              options={countryOptions}
              placeholder="Denmark"
            />
            <Switch label="Save my details" border={false} name="save_details" />
          </Fieldset>
        </FieldsContainer>
        <ActionsContainer>
          <Button icon="md-checkmark" iconPlacement="right" onPress={handleSubmit(onSubmit)} submitting={submitting}>Save</Button>
        </ActionsContainer>
      </Form>
    )
  }
}

export default reduxForm({
  form: 'Form',
  validate: values => {
    const errors = {}

    values = values.toJS()

    if (!values.first_name) {
      errors.first_name = 'First name is required.'
    }

    if (!values.last_name) {
      errors.last_name = 'Last name is required.'
    }

    if (!values.email) {
      errors.email = 'Email is required.'
    }

    return errors
  }
})(FormView)
```

<p>
  Easy, right?! Now we have a good looking form connected to the store with validation support and
  async button feedback.
  <a target="_blank" href="https://github.com/esbenp/react-native-clean-form">You can check out more of the features in the repository</a>.
</p>

## Conclusion

<p>

</p>

<p>
  Reach out on
  <a href="mailto:esbenspetersen@gmail.com">e-mail</a>,
  <a href="https://twitter.com/esbenp">twitter</a> or
  <a href="https://github.com/esbenp/react-native-redux-form-example/issues">the repository for this article</a>.
</p>
