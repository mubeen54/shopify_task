import {
    Card,
    Frame,
    IndexTable,
    Layout,
    Page,
    Spinner,
    Text,
    TextField,
    useIndexResourceState,
    Modal,
    DropZone,
    Thumbnail
} from '@shopify/polaris';
import { useEffect, useState, useCallback } from 'react';
import useAxios from '../hooks/useAxios';

const ProductCreator = () => {
    const [products, setProducts] = useState([]);
    const [loading, setLoading] = useState(true);
    const [searchQuery, setSearchQuery] = useState('');
    const [activeModal, setActiveModal] = useState(false);
    const [productToDelete, setProductToDelete] = useState(null);
    const { axios } = useAxios();

    const [loadingEdit, setLoadingEdit] = useState(false);
    const [loadingDelete, setLoadingDelete] = useState(false);

    const [file, setFile] = useState(null);

    // Fetch products with search query
    const fetchProducts = useCallback((query = '') => {
        setLoading(true);
        axios.get('/products', { params: { query } })
            .then(response => {
                setProducts(response.data);
                setLoading(false);
            })
            .catch(error => {
                console.error('Failed to fetch products:', error);
                setLoading(false);
            });
    }, [axios]);

    useEffect(() => {
        fetchProducts();
    }, [fetchProducts]);

    const handleSearchChange = useCallback((value) => {
        setSearchQuery(value);
        fetchProducts(value);
    }, [fetchProducts]);

    const resourceName = {
        singular: 'product',
        plural: 'products',
    };

    const {
        selectedResources,
        allResourcesSelected,
        handleSelectionChange
    } = useIndexResourceState(products);

    const handleDelete = async () => {
        setLoadingDelete(true);
        try {
            await axios.delete(`/products/${productToDelete}`);
            setProducts(prev => prev.filter(p => p.id !== productToDelete));
        } catch (error) {
            console.error('Failed to delete product:', error);
            alert('Failed to delete product');
        } finally {
            setLoadingDelete(false);
            setActiveModal(false);
            setProductToDelete(null);
        }
    };

    const [editModalActive, setEditModalActive] = useState(false);
    const [productToEdit, setProductToEdit] = useState(null);
    const [editTitle, setEditTitle] = useState('');
    const [editImage, setEditImage] = useState('');

    const handleEdit = async () => {
        setLoadingEdit(true);
        try {
            const payload = {
                title: editTitle,
                image_id: editImage,
            };
            console.log('Update Payload:', payload);

            await axios.put(`/products/${productToEdit.id}`, payload);

            setProducts(prev =>
                prev.map(p =>
                    p.id === productToEdit.id ? { ...p, title: editTitle, image: { src: editImage } } : p
                )
            );
        } catch (error) {
            console.error('Failed to update product:', error.response?.data || error.message);
            alert('Failed to update product');
        } finally {
            setLoadingEdit(false);
            setEditModalActive(false);
            setProductToEdit(null);
            setEditTitle('');
            setEditImage('');
        }
    };

    const handleDropZoneDrop = useCallback(async (_dropFiles, acceptedFiles) => {
        const uploadedFile = acceptedFiles[0];
        setFile(uploadedFile);

        const reader = new FileReader();
        reader.onloadend = async () => {
            try {
                // Step 1: Create staged upload
                const input = [
                    {
                        resource: "IMAGE",
                        filename: uploadedFile.name,
                        mimeType: uploadedFile.type,
                        fileSize: uploadedFile.size,
                    },
                ];
                console.log('Staged Upload Input:', input);

                const stagedUploadResponse = await axios.post('/staged-uploads-create', { input });
                console.log('Staged Upload Response:', stagedUploadResponse.data);

                const stagedTarget = stagedUploadResponse.data.stagedTargets[0];

                // Step 2: Upload file to the staged URL
                const formData = new FormData();
                stagedTarget.parameters.forEach(param => {
                    formData.append(param.name, param.value);
                });
                formData.append("file", uploadedFile);

                const uploadResponse = await fetch(stagedTarget.url, {
                    method: "POST",
                    body: formData,
                });

                if (!uploadResponse.ok) {
                    const errorText = await uploadResponse.text();
                    console.error('Staged URL Upload Error:', errorText);
                    throw new Error('Failed to upload file to staged URL');
                }

                // Step 3: Update collection with the uploaded image
                const collectionUpdateResponse = await axios.post('/collection-update', {
                    input: {
                        id: productToEdit.id,
                        image: {
                            src: stagedTarget.resourceUrl,
                        },
                    },
                });
                console.log('Collection Update Response:', collectionUpdateResponse.data);

                setEditImage(collectionUpdateResponse.data.collection.image.originalSrc);
            } catch (error) {
                console.error('Failed to upload image:', error.response?.data || error.message);
                alert('Failed to upload image');
            }
        };
        reader.readAsDataURL(uploadedFile);
    }, [axios, productToEdit]);

    const validImageTypes = ['image/gif', 'image/jpeg', 'image/png'];
    const fileUpload = !file && <DropZone.FileUpload />;
    const uploadedFile = file && validImageTypes.includes(file.type) ? (
        <Thumbnail
            size="large"
            alt={file.name}
            source={URL.createObjectURL(file)}
        />
    ) : null;

    return (
        <Frame>
            <Page title="Product List">
                <Layout>
                    <Layout.Section>
                        <TextField
                            label="Search"
                            value={searchQuery}
                            onChange={handleSearchChange}
                            placeholder="Search products..."
                            clearButton
                            onClearButtonClick={() => handleSearchChange('')}
                        />
                    </Layout.Section>

                    <Layout.Section>
                        {loading ? (
                            <Spinner accessibilityLabel="Loading products" size="large" />
                        ) : (
                            <Card>
                                {products.length > 0 ? (
                                    <IndexTable
                                        resourceName={resourceName}
                                        itemCount={products.length}
                                        selectedItemsCount={allResourcesSelected ? 'All' : selectedResources.length}
                                        onSelectionChange={handleSelectionChange}
                                        headings={[
                                            { title: 'Product' },
                                            { title: 'Image' },
                                            { title: 'ID' },
                                            { title: 'Actions' },
                                        ]}
                                    >
                                        {products.map((product, index) => (
                                            <IndexTable.Row
                                                id={product.id.toString()}
                                                key={product.id}
                                                selected={selectedResources.includes(product.id.toString())}
                                                position={index}
                                            >
                                                <IndexTable.Cell>{product.title}</IndexTable.Cell>
                                                <IndexTable.Cell>
                                                    {product.image?.src ? (
                                                        <img
                                                            src={product.image.src}
                                                            alt={product.title}
                                                            style={{ width: '50px', height: '50px', objectFit: 'cover', borderRadius: 4 }}
                                                        />
                                                    ) : (
                                                        <Text as="span" tone="subdued">No Image</Text>
                                                    )}
                                                </IndexTable.Cell>
                                                <IndexTable.Cell>{product.id}</IndexTable.Cell>
                                                <IndexTable.Cell>
                                                    <button
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            setProductToEdit(product);
                                                            setEditTitle(product.title);
                                                            setEditImage(product.image?.src || '');
                                                            setEditModalActive(true);
                                                        }}
                                                        style={{
                                                            background: '#006fbb',
                                                            color: 'white',
                                                            padding: '6px 12px',
                                                            border: 'none',
                                                            borderRadius: '4px',
                                                            cursor: loadingEdit ? 'not-allowed' : 'pointer',
                                                            marginRight: '10px',
                                                        }}
                                                        disabled={loadingEdit}
                                                    >
                                                        {loadingEdit ? (
                                                            <Spinner size="small" color="white" />
                                                        ) : (
                                                            'Edit'
                                                        )}
                                                    </button>

                                                    <button
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            setProductToDelete(product.id);
                                                            setActiveModal(true);
                                                        }}
                                                        style={{
                                                            background: '#bf0711',
                                                            color: 'white',
                                                            padding: '6px 12px',
                                                            border: 'none',
                                                            borderRadius: '4px',
                                                            cursor: loadingDelete ? 'not-allowed' : 'pointer',
                                                        }}
                                                        disabled={loadingDelete}
                                                    >
                                                        {loadingDelete ? (
                                                            <Spinner size="small" color="white" />
                                                        ) : (
                                                            'Delete'
                                                        )}
                                                    </button>
                                                </IndexTable.Cell>
                                            </IndexTable.Row>
                                        ))}
                                    </IndexTable>
                                ) : (
                                    <Text as="p" alignment="center">
                                        No products found.
                                    </Text>
                                )}
                            </Card>
                        )}
                    </Layout.Section>
                </Layout>

                {/* Delete Product Modal */}
                <Modal
                    open={activeModal}
                    onClose={() => {
                        setActiveModal(false);
                        setProductToDelete(null);
                    }}
                    title="Are you sure you want to delete this product?"
                    primaryAction={{
                        content: loadingDelete ? 'Deleting...' : 'Delete',
                        onAction: handleDelete,
                        destructive: true,
                        loading: loadingDelete,
                    }}
                    secondaryActions={[
                        {
                            content: 'Cancel',
                            onAction: () => {
                                setActiveModal(false);
                                setProductToDelete(null);
                            },
                        },
                    ]}
                >
                    <Modal.Section>
                        <Text as="p">This action cannot be undone.</Text>
                    </Modal.Section>
                </Modal>

                {/* Edit Product Modal */}
                <Modal
                    open={editModalActive}
                    onClose={() => {
                        setEditModalActive(false);
                        setProductToEdit(null);
                        setEditTitle('');
                        setEditImage('');
                    }}
                    title="Edit Product"
                    primaryAction={{
                        content: loadingEdit ? 'Saving...' : 'Save',
                        onAction: handleEdit,
                        loading: loadingEdit,
                    }}
                    secondaryActions={[
                        {
                            content: 'Cancel',
                            onAction: () => {
                                setEditModalActive(false);
                                setProductToEdit(null);
                                setEditTitle('');
                                setEditImage('');
                            },
                        },
                    ]}
                >
                    <Modal.Section>
                        <TextField
                            label="Product Title"
                            value={editTitle}
                            onChange={setEditTitle}
                            autoFocus
                        />
                        <div style={{ marginTop: '10px', textAlign: 'center' }}>
                            {editImage && (
                                <img
                                    src={editImage}
                                    alt="Product Preview"
                                    style={{
                                        maxWidth: '100%',
                                        maxHeight: '200px',
                                        objectFit: 'contain',
                                        border: '1px solid #ccc',
                                        borderRadius: '4px',
                                        padding: '5px',
                                    }}
                                />
                            )}
                            <DropZone onDrop={handleDropZoneDrop} accept="image/*" type="image">
                                {uploadedFile}
                                {fileUpload}
                            </DropZone>
                        </div>
                    </Modal.Section>
                </Modal>
            </Page>
        </Frame>
    );
};

export default ProductCreator;