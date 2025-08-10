Streaming is a kind of special case where you want to be able to show progress to the user. An AI provider that can stream actually works by figuring out the next token in the response continuously, and then sending that token to the client as soon as it is available. This allows you to show the user a response that is being built up in real-time.

Streaming however comes with some challenges when you are developing for it. If you were to use the same approach as with a normal chat call, you would end the actual response and headers when the first token is sent, and then any processes after that would be problematic.

We try to add helper functions for this.

## StreamedChatMessageIterator

Each of the providers that can do streaming will return back a StreamedChatMessageIterator object instead of a ChatMessage object. This is an iterator that will yield ChatMessage objects as they are received from the provider.

This means that you can easily just use a foreach loop to iterate over the messages as they are received.

```
$stream = $response->getNormalized();
foreach ($stream as $message) {
  // Do something with the message.
  // For example, you can print the message text.
  echo $message->getText();
}
```

## Post streaming

Post streaming there are a couple of things you can still do with the StreamedChatMessageIterator. You can get the total token usage, input token usage, output token usage, cached token usage, and reasoning token usage.

You can also request to get a built ChatOutput object that contains all the messages that were received during the streaming process using the `getChatOutput()` method. This will return a ChatOutput object that contains all the messages that were received during the streaming process.

Example:
```
$stream = $response->getNormalized();
foreach ($stream as $message) {
  // Do something with the message.
  echo $message->getText();
}
$output = $stream->getChatOutput();
echo $output->getTotalTokenUsage();
$messages = $output->getMessage();
```

## Event Dispatching

There also exists an event that is dispatched after the streaming is done. This event is called `PostStreamingResponseEvent` and it contains the request thread ID, the ChatOutput object, and any additional data that you want to pass along.
