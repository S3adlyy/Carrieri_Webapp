from ollama import chat

response = chat(
    model='gemma2:2b',
    messages=[
        {'role': 'user', 'content': 'Donne un titre d offre pour un developpeur Symfony senior a Tunis'}
    ]
)

print(response['message']['content'])
