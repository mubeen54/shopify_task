import { ApolloClient, InMemoryCache, ApolloProvider } from '@apollo/client';

const client = new ApolloClient({
    uri: '/graphql', // Replace with your GraphQL endpoint
    cache: new InMemoryCache(),
});

export default client;
