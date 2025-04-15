import { Page, Layout, TextField, Button, Card } from '@shopify/polaris';
import { useState } from 'react';
import useAxios from '../hooks/useAxios';

const ProductForm = () => {
    const [title, setTitle] = useState('hello');
    const [count, setCount] = useState('2');
    const [imageUrl, setImageUrl] = useState('');
    const [loading, setLoading] = useState(false);
    const { axios } = useAxios();

    const handleImageUpload = async (file) => {
        if (!file) {
            alert('Please select an image file.');
            return;
        }

        const formData = new FormData();
        formData.append('image', file);

        console.log('FormData content:', formData.get('image')); // Log the file to verify

        setLoading(true);
        try {
            console.log('Uploading image:', file);
            const response = await axios.post('/hello/upload-image', formData, {
                headers: {  'Content-Type': 'multipart/form-data' },
            });
            setImageUrl(response.data.imageUrl);
            alert('Image uploaded successfully!');
        } catch (error) {
            console.error('Failed to upload image:', error.response?.data || error.message);
            alert('Failed to upload image');
        } finally {
            setLoading(false);
        }
    };

    const handleSubmit = async () => {
        if (!imageUrl) {
            alert('Please upload an image before submitting.');
            return;
        }

        setLoading(true);
        try {
            const payload = { title, count, image: imageUrl };
            console.log('Submitting product:', payload);
            const response = await axios.post('/product/store', payload);
            alert('Product created successfully!');
            setTitle('');
            setCount('');
            setImageUrl('');
        } catch (error) {
            console.error('Failed to create product:', error.response?.data || error.message);
            alert('Failed to create product');
        } finally {
            setLoading(false);
        }
    };

    return (
        <Page title="Create Product">
            <Layout>
                <Layout.Section>
                    <Card sectioned>
                        <TextField
                            label="Product Title"
                            value={title}
                            onChange={setTitle}
                            autoComplete="off"
                        />
                        <TextField
                            label="Product Count"
                            type="number"
                            value={count}
                            onChange={setCount}
                            autoComplete="off"
                        />
                        <input
                            type="file"
                            accept="image/*"
                            onChange={(e) => handleImageUpload(e.target.files[0])}
                            style={{ marginTop: '20px' }}
                        />
                        {imageUrl && <p>Image uploaded: {imageUrl}</p>}
                        <div style={{ marginTop: '20px' }}>
                            <Button
                                primary
                                onClick={handleSubmit}
                                loading={loading}
                                disabled={!title.trim() || !count.trim() || !imageUrl}
                            >
                                Save
                            </Button>
                        </div>
                    </Card>
                </Layout.Section>
            </Layout>
        </Page>
    );
};

export default ProductForm;
